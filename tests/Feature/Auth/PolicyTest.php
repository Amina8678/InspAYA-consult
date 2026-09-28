<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\ContactSubmissionNote;
use App\Models\Media;
use App\Models\Role;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Scope rules from plan §6 that go beyond a plain permission check.
 */
class PolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function as(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }

    private function can(User $user, string $ability, mixed $arguments = []): bool
    {
        return Gate::forUser($user)->allows($ability, $arguments);
    }

    public function test_author_edits_only_own_unpublished_posts(): void
    {
        $author = $this->as('author');
        $own = BlogPost::factory()->for($author, 'author')->create();
        $ownPublished = BlogPost::factory()->for($author, 'author')->published()->create();
        $other = BlogPost::factory()->create();

        $this->assertTrue($this->can($author, 'update', $own));
        $this->assertTrue($this->can($author, 'submitForReview', $own));
        $this->assertFalse($this->can($author, 'update', $ownPublished));
        $this->assertTrue($this->can($author, 'view', $ownPublished));
        $this->assertFalse($this->can($author, 'update', $other));
        $this->assertFalse($this->can($author, 'view', $other));
        $this->assertFalse($this->can($author, 'delete', $own));
        $this->assertFalse($this->can($author, 'publish', $own));
    }

    public function test_editor_edits_any_post_but_cannot_publish(): void
    {
        $editor = $this->as('editor');
        $post = BlogPost::factory()->inReview()->create();

        $this->assertTrue($this->can($editor, 'update', $post));
        $this->assertTrue($this->can($editor, 'delete', $post));
        $this->assertFalse($this->can($editor, 'publish', $post));
        $this->assertFalse($this->can($editor, 'publish', \App\Models\Page::factory()->create()));
    }

    public function test_administrator_publishes(): void
    {
        $this->assertTrue($this->can($this->as('administrator'), 'publish', BlogPost::factory()->inReview()->create()));
    }

    public function test_editor_edits_services_but_does_not_manage_them(): void
    {
        $editor = $this->as('editor');
        $service = Service::factory()->create();

        $this->assertTrue($this->can($editor, 'update', $service));
        $this->assertFalse($this->can($editor, 'create', Service::class));
        $this->assertFalse($this->can($editor, 'delete', $service));
        $this->assertFalse($this->can($editor, 'changeStatus', $service));
        $this->assertTrue($this->can($this->as('administrator'), 'changeStatus', $service));
    }

    public function test_author_manages_only_own_media(): void
    {
        $author = $this->as('author');
        $own = Media::factory()->create(['uploader_id' => $author->id]);
        $other = Media::factory()->create();

        $this->assertTrue($this->can($author, 'create', Media::class));
        $this->assertTrue($this->can($author, 'update', $own));
        $this->assertTrue($this->can($author, 'delete', $own));
        $this->assertFalse($this->can($author, 'delete', $other));
        $this->assertTrue($this->can($this->as('editor'), 'delete', $other));
    }

    public function test_administrator_cannot_act_on_super_admins_or_grant_super_admin(): void
    {
        $admin = $this->as('administrator');
        $superAdmin = $this->as('super-admin');
        $editor = $this->as('editor');
        $superRole = Role::firstWhere('slug', 'super-admin');
        $editorRole = Role::firstWhere('slug', 'editor');

        $this->assertFalse($this->can($admin, 'update', $superAdmin));
        $this->assertFalse($this->can($admin, 'deactivate', $superAdmin));
        $this->assertFalse($this->can($admin, 'resetPassword', $superAdmin));
        $this->assertFalse($this->can($admin, 'assignRole', [$editor, $superRole]));

        $this->assertTrue($this->can($admin, 'update', $editor));
        $this->assertTrue($this->can($admin, 'assignRole', [$editor, $editorRole]));
        $this->assertTrue($this->can($superAdmin, 'assignRole', [$editor, $superRole]));
    }

    public function test_nobody_changes_their_own_role_deactivates_themselves_or_deletes_users(): void
    {
        $superAdmin = $this->as('super-admin');

        $this->assertFalse($this->can($superAdmin, 'assignRole', [$superAdmin, Role::firstWhere('slug', 'editor')]));
        $this->assertFalse($this->can($superAdmin, 'deactivate', $superAdmin));
        $this->assertFalse($this->can($superAdmin, 'delete', $this->as('author')));
    }

    public function test_only_super_admin_edits_roles_and_never_the_super_admin_role(): void
    {
        $superAdmin = $this->as('super-admin');
        $editorRole = Role::firstWhere('slug', 'editor');

        $this->assertTrue($this->can($superAdmin, 'update', $editorRole));
        $this->assertFalse($this->can($superAdmin, 'update', Role::firstWhere('slug', 'super-admin')));
        $this->assertFalse($this->can($this->as('administrator'), 'update', $editorRole));
        $this->assertTrue($this->can($this->as('administrator'), 'viewAny', Role::class));
    }

    public function test_audit_logs_are_readable_by_admins_and_immutable_for_everyone(): void
    {
        $log = AuditLog::factory()->create();

        $this->assertTrue($this->can($this->as('administrator'), 'viewAny', AuditLog::class));
        $this->assertFalse($this->can($this->as('editor'), 'viewAny', AuditLog::class));
        $this->assertFalse($this->can($this->as('super-admin'), 'update', $log));
        $this->assertFalse($this->can($this->as('super-admin'), 'delete', $log));
    }

    public function test_settings_are_edited_not_created_or_deleted(): void
    {
        $setting = SiteSetting::factory()->create();
        $admin = $this->as('administrator');

        $this->assertTrue($this->can($admin, 'update', $setting));
        $this->assertFalse($this->can($admin, 'delete', $setting));
        $this->assertFalse($this->can($this->as('editor'), 'update', $setting));
    }

    public function test_enquiry_notes_are_edited_only_by_their_author(): void
    {
        $editor = $this->as('editor');
        $own = ContactSubmissionNote::factory()->create(['user_id' => $editor->id]);
        $other = ContactSubmissionNote::factory()->create();

        $this->assertTrue($this->can($editor, 'update', $own));
        $this->assertFalse($this->can($editor, 'update', $other));
        $this->assertFalse($this->can($editor, 'delete', $own));
    }
}
