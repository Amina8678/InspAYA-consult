<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every role × permission, checked through the Gate against schema plan §6.
 * The expected grid is written out here independently of the seeder, so a
 * change to either one fails this test.
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** Plan §6: [permission => [super-admin, administrator, editor, author]] */
    private const PLAN = [
        'users.view' => [1, 1, 0, 0],
        'users.create' => [1, 1, 0, 0],
        'users.update' => [1, 1, 0, 0],
        'users.deactivate' => [1, 1, 0, 0],
        'users.assign-role' => [1, 1, 0, 0],
        'users.reset-password' => [1, 1, 0, 0],
        'roles.manage' => [1, 0, 0, 0],
        'settings.manage' => [1, 1, 0, 0],
        'services.edit' => [1, 1, 1, 0],
        'services.manage' => [1, 1, 0, 0],
        'values.edit' => [1, 1, 1, 0],
        'values.manage' => [1, 1, 0, 0],
        'consultants.edit' => [1, 1, 1, 0],
        'consultants.manage' => [1, 1, 0, 0],
        'pages.edit' => [1, 1, 1, 0],
        'pages.manage' => [1, 1, 0, 0],
        'posts.create' => [1, 1, 1, 1],
        'posts.edit-own' => [1, 1, 1, 1],
        'posts.edit-any' => [1, 1, 1, 0],
        'posts.delete' => [1, 1, 1, 0],
        'taxonomy.manage' => [1, 1, 1, 0],
        'content.publish' => [1, 1, 0, 0],
        'media.view' => [1, 1, 1, 1],
        'media.upload' => [1, 1, 1, 1],
        'media.manage-own' => [1, 1, 1, 1],
        'media.manage' => [1, 1, 1, 0],
        'enquiries.view' => [1, 1, 1, 0],
        'enquiries.respond' => [1, 1, 1, 0],
        'enquiries.assign' => [1, 1, 0, 0],
        'enquiries.delete' => [1, 1, 0, 0],
        'enquiries.export' => [1, 1, 0, 0],
        'audit-logs.view' => [1, 1, 0, 0],
    ];

    private const ROLES = ['super-admin', 'administrator', 'editor', 'author'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function matrix(): array
    {
        $cases = [];

        foreach (self::PLAN as $permission => $grants) {
            foreach (self::ROLES as $i => $role) {
                $cases["{$role} {$permission}"] = [$role, $permission, (bool) $grants[$i]];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_role_has_exactly_the_planned_permissions(string $role, string $permission, bool $expected): void
    {
        $user = User::factory()->withRole($role)->create();

        $this->assertSame($expected, Gate::forUser($user)->allows($permission));
    }

    public function test_plan_covers_every_seeded_permission(): void
    {
        $this->assertEqualsCanonicalizing(array_keys(self::PLAN), Permission::pluck('slug')->all());
    }

    public function test_unknown_permissions_are_denied_even_to_super_admin(): void
    {
        $admin = User::factory()->withRole('super-admin')->create();

        $this->assertFalse(Gate::forUser($admin)->allows('posts.fly'));
    }

    public function test_inactive_user_is_denied_everything(): void
    {
        $admin = User::factory()->withRole('super-admin')->inactive()->create();

        $this->assertFalse(Gate::forUser($admin)->allows('posts.create'));
        $this->assertFalse(Gate::forUser($admin)->allows('viewAny', \App\Models\BlogPost::class));
    }

    public function test_permission_changes_apply_on_the_next_request_without_stale_cache(): void
    {
        $editor = User::factory()->withRole('editor')->create();
        $this->assertTrue(Gate::forUser($editor)->allows('media.manage'));

        Role::firstWhere('slug', 'editor')->permissions()
            ->detach(Permission::firstWhere('slug', 'media.manage'));

        // A new request loads a fresh user; nothing is cached across requests.
        $this->assertFalse(Gate::forUser($editor->fresh())->allows('media.manage'));
    }

    public function test_role_change_applies_immediately_on_the_same_instance(): void
    {
        $user = User::factory()->withRole('author')->create();
        $this->assertFalse($user->hasPermission('settings.manage'));

        $user->role_id = Role::firstWhere('slug', 'administrator')->id;

        $this->assertTrue($user->hasPermission('settings.manage'));
    }
}
