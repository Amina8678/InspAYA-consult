<?php

namespace Tests\Feature\Database;

use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Consultant;
use App\Models\ContactSubmission;
use App\Models\ContactSubmissionNote;
use App\Models\CoreValue;
use App\Models\Media;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Database-level integrity: unique indexes and FK delete behaviour exactly as
 * declared in the migrations. Runs on SQLite and MySQL.
 */
#[Group('constraints')]
class ConstraintTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function uniqueColumns(): array
    {
        return [
            'roles.slug' => [Role::class, 'slug'],
            'permissions.slug' => [Permission::class, 'slug'],
            'users.username' => [User::class, 'username'],
            'users.email' => [User::class, 'email'],
            'media.storage_path' => [Media::class, 'storage_path'],
            'pages.slug' => [Page::class, 'slug'],
            'services.slug' => [Service::class, 'slug'],
            'core_values.slug' => [CoreValue::class, 'slug'],
            'categories.slug' => [Category::class, 'slug'],
            'tags.slug' => [Tag::class, 'slug'],
            'blog_posts.slug' => [BlogPost::class, 'slug'],
            'site_settings.key' => [SiteSetting::class, 'key'],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('uniqueColumns')]
    public function test_unique_column_rejects_duplicates(string $model, string $column): void
    {
        $existing = $model::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        $model::factory()->create([$column => $existing->{$column}]);
    }

    public function test_orphan_foreign_key_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        BlogPost::factory()->create(['author_id' => 999_999]);
    }

    public function test_pivot_rejects_orphan_rows(): void
    {
        $service = Service::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('service_consultant')->insert([
            'service_id' => $service->id,
            'consultant_id' => 999_999,
        ]);
    }

    public function test_role_held_by_a_user_cannot_be_deleted(): void
    {
        $role = Role::factory()->create();
        User::factory()->withRole($role)->create();

        try {
            $role->delete();
            $this->fail('Deleting a role held by a user should be restricted.');
        } catch (QueryException) {
            $this->assertModelExists($role);
        }
    }

    public function test_unused_role_can_be_deleted_and_cascades_its_permissions(): void
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::factory()->create());

        $role->delete();

        $this->assertModelMissing($role);
        $this->assertDatabaseMissing('permission_role', ['role_id' => $role->id]);
    }

    public function test_author_with_posts_cannot_be_deleted(): void
    {
        $post = BlogPost::factory()->create();
        $author = $post->author;

        try {
            $author->delete();
            $this->fail('Deleting a user who authored posts should be restricted.');
        } catch (QueryException) {
            $this->assertModelExists($author);
            $this->assertModelExists($post);
        }
    }

    public function test_deleting_media_nulls_every_image_reference(): void
    {
        $media = Media::factory()->create();
        $post = BlogPost::factory()->create(['featured_image_id' => $media->id]);
        $consultant = Consultant::factory()->create(['photo_id' => $media->id]);
        $value = CoreValue::factory()->create(['icon_id' => $media->id]);
        $setting = SiteSetting::factory()->media()->create(['media_id' => $media->id]);

        $media->delete();

        $this->assertNull($post->fresh()->featured_image_id);
        $this->assertNull($consultant->fresh()->photo_id);
        $this->assertNull($value->fresh()->icon_id);
        $this->assertNull($setting->fresh()->media_id);
    }

    public function test_deleting_a_user_without_posts_nulls_attribution_but_keeps_records(): void
    {
        $user = User::factory()->create();
        $media = Media::factory()->create(['uploader_id' => $user->id]);
        $enquiry = ContactSubmission::factory()->assignedTo($user)->create();
        $note = ContactSubmissionNote::factory()->create(['user_id' => $user->id]);
        $log = AuditLog::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertNull($media->fresh()->uploader_id);
        $this->assertNull($enquiry->fresh()->assigned_to);
        $this->assertNull($note->fresh()->user_id);
        $this->assertNull($log->fresh()->user_id);
    }

    public function test_deleting_a_category_leaves_posts_uncategorised(): void
    {
        $post = BlogPost::factory()->categorised()->create();

        $post->category->delete();

        $this->assertModelExists($post);
        $this->assertNull($post->fresh()->category_id);
    }

    public function test_deleting_a_user_deletes_nothing_but_their_own_row_and_role_link(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->withRole($role)->create();

        $user->delete();

        $this->assertModelExists($role);
    }

    public function test_service_consultant_assignments_cascade_from_both_sides(): void
    {
        [$serviceA, $serviceB] = Service::factory()->count(2)->create();
        [$consultantA, $consultantB] = Consultant::factory()->count(2)->create();
        $serviceA->consultants()->attach([$consultantA->id, $consultantB->id]);
        $serviceB->consultants()->attach($consultantA->id);

        $serviceA->delete();
        $this->assertDatabaseMissing('service_consultant', ['service_id' => $serviceA->id]);
        $this->assertDatabaseHas('service_consultant', ['service_id' => $serviceB->id]);

        $consultantA->delete();
        $this->assertDatabaseCount('service_consultant', 0);
        $this->assertModelExists($consultantB);
    }

    public function test_post_tags_cascade_from_both_sides(): void
    {
        $post = BlogPost::factory()->create();
        [$tagA, $tagB] = Tag::factory()->count(2)->create();
        $post->tags()->attach([$tagA->id, $tagB->id]);

        $tagA->delete();
        $this->assertDatabaseCount('blog_post_tag', 1);

        $post->delete();
        $this->assertDatabaseCount('blog_post_tag', 0);
        $this->assertModelExists($tagB);
    }

    public function test_deleting_a_permission_cascades_its_role_assignments(): void
    {
        $permission = Permission::factory()->create();
        $role = Role::factory()->create();
        $role->permissions()->attach($permission);

        $permission->delete();

        $this->assertDatabaseMissing('permission_role', ['permission_id' => $permission->id]);
        $this->assertModelExists($role);
    }

    public function test_deleting_an_enquiry_deletes_its_notes(): void
    {
        $note = ContactSubmissionNote::factory()->create();

        $note->submission->delete();

        $this->assertModelMissing($note);
    }

    public function test_enquiry_requires_consent_timestamp(): void
    {
        $this->expectException(QueryException::class);

        ContactSubmission::factory()->create(['consent_at' => null]);
    }
}
