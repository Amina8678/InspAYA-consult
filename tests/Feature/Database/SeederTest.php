<?php

namespace Tests\Feature\Database;

use App\Models\BlogPost;
use App\Models\Consultant;
use App\Models\CoreValue;
use App\Models\Media;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\SiteSettingsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    private string $password;

    protected function setUp(): void
    {
        parent::setUp();

        // Generated per run: no credential is stored in the repository.
        $this->password = Str::password(24);

        config(['inspaya.super_admin' => [
            'name' => 'Test Super Admin',
            'username' => 'test-super-admin',
            'email' => 'super-admin@example.com',
            'password' => $this->password,
        ]]);
    }

    public function test_database_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $first = $this->rowCounts();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($first, $this->rowCounts());
        $this->assertGreaterThan(0, $first['blog_post_tag'], 'Sanity check: demo content was seeded.');
    }

    public function test_reseeding_preserves_cms_edits_and_the_super_admin_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        SiteSetting::where('key', 'contact.email')->update(['value' => 'changed@example.com']);
        Service::where('slug', 'corporate-legal-consultations')->update(['title' => 'Edited title']);
        User::where('email', 'super-admin@example.com')->update(['password' => Hash::make('rotated-password-123')]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('changed@example.com', SiteSetting::where('key', 'contact.email')->value('value'));
        $this->assertSame('Edited title', Service::where('slug', 'corporate-legal-consultations')->value('title'));
        $this->assertTrue(Hash::check('rotated-password-123', User::firstWhere('email', 'super-admin@example.com')->password));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function superAdminKeys(): array
    {
        return [
            'name' => ['name'],
            'username' => ['username'],
            'email' => ['email'],
            'password' => ['password'],
        ];
    }

    #[DataProvider('superAdminKeys')]
    public function test_super_admin_seeder_fails_when_an_env_var_is_unset(string $key): void
    {
        config(["inspaya.super_admin.{$key}" => null]);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SUPER_ADMIN_'.strtoupper($key));

        $this->seed(SuperAdminSeeder::class);
    }

    public function test_super_admin_seeder_names_every_missing_env_var(): void
    {
        config(['inspaya.super_admin' => ['name' => null, 'username' => null, 'email' => null, 'password' => null]]);
        $this->seed(RolesAndPermissionsSeeder::class);

        try {
            $this->seed(SuperAdminSeeder::class);
            $this->fail('Seeding without credentials should fail.');
        } catch (RuntimeException $e) {
            foreach (['SUPER_ADMIN_NAME', 'SUPER_ADMIN_USERNAME', 'SUPER_ADMIN_EMAIL', 'SUPER_ADMIN_PASSWORD'] as $env) {
                $this->assertStringContainsString($env, $e->getMessage());
            }
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_super_admin_seeder_rejects_a_short_password(): void
    {
        config(['inspaya.super_admin.password' => 'short']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('at least 12 characters');

        $this->seed(SuperAdminSeeder::class);
    }

    public function test_super_admin_is_created_from_config_with_a_hashed_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::firstWhere('email', 'super-admin@example.com');

        $this->assertSame('super-admin', $admin->role->slug);
        $this->assertSame('test-super-admin', $admin->username);
        $this->assertNotSame($this->password, $admin->password);
        $this->assertTrue(Hash::check($this->password, $admin->password));
        $this->assertSame(1, User::count());
    }

    public function test_role_permission_matrix(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $granted = fn (string $role) => Role::firstWhere('slug', $role)->permissions->pluck('slug');

        $this->assertSame(4, Role::count());
        $this->assertSame(32, Permission::count());
        $this->assertCount(32, $granted('super-admin'));
        $this->assertCount(31, $granted('administrator'));
        $this->assertNotContains('roles.manage', $granted('administrator'));
        $this->assertContains('content.publish', $granted('administrator'));

        $this->assertNotContains('content.publish', $granted('editor'), 'Editors must not publish until "approved" is defined (D12).');
        $this->assertNotContains('settings.manage', $granted('editor'));
        $this->assertNotContains('enquiries.assign', $granted('editor'));
        $this->assertContains('enquiries.respond', $granted('editor'));

        $this->assertEqualsCanonicalizing(
            ['posts.create', 'posts.edit-own', 'media.view', 'media.upload', 'media.manage-own'],
            $granted('author')->all(),
        );
    }

    public function test_seven_core_services_are_seeded_with_placeholder_copy(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(ServiceSeeder::SERVICES, Service::orderBy('sort_order')->pluck('title')->all());
        Service::all()->each(function (Service $service) {
            $this->assertStringContainsString('[PLACEHOLDER', $service->description);
            $this->assertTrue($service->is_active);
        });
    }

    public function test_settings_contain_no_real_contact_details(): void
    {
        $this->seed(SiteSettingsSeeder::class);

        $this->assertSame(count(SiteSettingsSeeder::SETTINGS), SiteSetting::count());

        $values = SiteSetting::pluck('value')->filter();
        $emails = $values->filter(fn ($v) => str_contains($v, '@'));

        $this->assertNotEmpty($emails);
        $emails->each(fn ($email) => $this->assertStringEndsWith('@example.com', $email));
        $this->assertSame('+1 555 0100', SiteSetting::where('key', 'contact.phone')->value('value'));
    }

    public function test_non_production_seeds_demo_content_without_image_references(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(6, CoreValue::count());
        $this->assertSame(5, Page::count());
        $this->assertSame(4, Consultant::count());
        $this->assertSame(5, BlogPost::count());

        $this->assertSame(0, Media::count());
        $this->assertSame(0, BlogPost::whereNotNull('featured_image_id')->count());
        $this->assertSame(0, Consultant::whereNotNull('photo_id')->count());
        $this->assertSame(0, CoreValue::whereNotNull('icon_id')->count());
        $this->assertSame(0, SiteSetting::whereNotNull('media_id')->count());
        $this->assertStringNotContainsString('storage/', json_encode(Page::pluck('structured_content')));
        $this->assertStringNotContainsString('asset/images', json_encode(Page::pluck('structured_content')));

        $this->assertSame(0, Page::whereIn('slug', ['privacy-policy', 'terms-of-service'])->where('status', 'published')->count());
    }

    public function test_production_seeds_no_demo_content(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertTrue(app()->isProduction());

        // Production requires --force, as a real deploy would pass.
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertSame(4, Role::count());
        $this->assertSame(1, User::count());
        $this->assertSame(7, Service::count());
        $this->assertGreaterThan(0, SiteSetting::count());

        foreach (['core_values', 'pages', 'consultants', 'service_consultant', 'categories', 'tags', 'blog_posts', 'blog_post_tag'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    /**
     * @return array<string, int>
     */
    private function rowCounts(): array
    {
        // Current schema only: on MySQL the default listing spans every
        // database on the server.
        $tables = collect(Schema::getTableListing(Schema::getCurrentSchemaName(), schemaQualified: false))
            ->reject(fn (string $t) => $t === 'migrations')
            ->sort()
            ->values();

        return $tables->mapWithKeys(fn (string $t) => [$t => DB::table($t)->count()])->all();
    }
}
