<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * SRS §7 roles and the permission mapping from schema plan §6.
 *
 * Idempotent: roles and permissions are upserted by slug and each role's
 * permissions are synced, so the matrix below is the source of truth and
 * rerunning resets any drift.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * @var array<string, list<string>> group => permission slugs
     */
    public const PERMISSIONS = [
        'users' => [
            'users.view',
            'users.create',
            'users.update',
            'users.deactivate',
            'users.assign-role',
            'users.reset-password',
            'roles.manage',
        ],
        'settings' => [
            'settings.manage',
        ],
        'content' => [
            'services.edit',
            'services.manage',
            'values.edit',
            'values.manage',
            'consultants.edit',
            'consultants.manage',
            'pages.edit',
            'pages.manage',
            'content.publish',
        ],
        'posts' => [
            'posts.create',
            'posts.edit-own',
            'posts.edit-any',
            'posts.delete',
            'taxonomy.manage',
        ],
        'media' => [
            'media.view',
            'media.upload',
            'media.manage-own',
            'media.manage',
        ],
        'enquiries' => [
            'enquiries.view',
            'enquiries.respond',
            'enquiries.assign',
            'enquiries.delete',
            'enquiries.export',
        ],
        'audit' => [
            'audit-logs.view',
        ],
    ];

    /**
     * @var array<string, array{name: string, description: string}>
     */
    public const ROLES = [
        'super-admin' => [
            'name' => 'Super Admin',
            'description' => 'Full system access, including users, roles and audit logs.',
        ],
        'administrator' => [
            'name' => 'Administrator',
            'description' => 'Full content and settings access; user management without role editing.',
        ],
        'editor' => [
            'name' => 'Editor',
            'description' => 'Edits content, services, values and team; manages enquiries.',
        ],
        'author' => [
            'name' => 'Author',
            'description' => 'Drafts blog posts and manages their own posts and uploads.',
        ],
    ];

    /**
     * Role slug => granted permission slugs. Super Admin gets every permission.
     *
     * Editor has no content.publish until the SRS §7 "approved items only"
     * rule is defined (schema plan D12).
     *
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        $all = array_merge(...array_values(self::PERMISSIONS));

        return [
            'super-admin' => $all,
            'administrator' => array_values(array_diff($all, ['roles.manage'])),
            'editor' => [
                'services.edit',
                'values.edit',
                'consultants.edit',
                'pages.edit',
                'posts.create',
                'posts.edit-own',
                'posts.edit-any',
                'posts.delete',
                'taxonomy.manage',
                'media.view',
                'media.upload',
                'media.manage-own',
                'media.manage',
                'enquiries.view',
                'enquiries.respond',
            ],
            'author' => [
                'posts.create',
                'posts.edit-own',
                'media.view',
                'media.upload',
                'media.manage-own',
            ],
        ];
    }

    public function run(): void
    {
        $permissionIds = [];

        foreach (self::PERMISSIONS as $group => $slugs) {
            foreach ($slugs as $slug) {
                $permissionIds[$slug] = Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => Str::headline(str_replace('.', ' ', $slug)), 'group' => $group],
                )->id;
            }
        }

        foreach (self::matrix() as $slug => $granted) {
            $role = Role::updateOrCreate(['slug' => $slug], self::ROLES[$slug]);

            $role->permissions()->sync(array_map(fn (string $p) => $permissionIds[$p], $granted));
        }
    }
}
