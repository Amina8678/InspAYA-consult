<?php

namespace Tests\Feature\Admin;

use App\Models\CoreValue;
use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every admin route: guest → 302 to login, a role without access → 403, an
 * allowed role → 200 (or its success redirect). Plus: the route list is
 * complete, views never use {!! !!}, and no dead admin routes remain.
 */
class AdminRoutesTest extends TestCase
{
    use RefreshDatabase;

    /** A CMS role with no permissions at all. */
    private const NONE = 'no-permissions';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Role::factory()->create(['slug' => self::NONE, 'name' => 'No permissions']);
        Storage::fake('public');
    }

    /**
     * [method, path (":media" = someone else's file), allowed roles, denied roles, allowed status]
     *
     * @return array<string, array{string, string, list<string>, list<string>, int}>
     */
    public static function adminRoutes(): array
    {
        $everyone = ['super-admin', 'administrator', 'editor', 'author'];

        return [
            'dashboard' => ['GET', '/admin', [...$everyone, self::NONE], [], 200],
            'change password page' => ['GET', '/admin/account/password', [...$everyone, self::NONE], [], 200],
            'change password' => ['PUT', '/admin/account/password', $everyone, [], 302],
            'media list' => ['GET', '/admin/media', $everyone, [self::NONE], 200],
            'media upload' => ['POST', '/admin/media', $everyone, [self::NONE], 302],
            'media edit' => ['GET', '/admin/media/:media/edit', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'media update' => ['PUT', '/admin/media/:media', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'media delete confirm' => ['GET', '/admin/media/:media/delete', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'media destroy' => ['DELETE', '/admin/media/:media', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'settings page' => ['GET', '/admin/settings', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'settings save' => ['PUT', '/admin/settings', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'core values list' => ['GET', '/admin/core-values', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'core value create page' => ['GET', '/admin/core-values/create', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'core value store' => ['POST', '/admin/core-values', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'core value edit' => ['GET', '/admin/core-values/:value/edit', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'core value update' => ['PUT', '/admin/core-values/:value', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'core value move' => ['POST', '/admin/core-values/:value/move', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'core value delete confirm' => ['GET', '/admin/core-values/:value/delete', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'core value destroy' => ['DELETE', '/admin/core-values/:value', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
        ];
    }

    /** Route placeholder => route parameter as registered. */
    private const PLACEHOLDERS = [':media' => '{media}', ':value' => '{coreValue}'];

    private function hit(string $method, string $path)
    {
        $media = Media::factory()->create(['alt_text' => 'x']);
        Storage::disk('public')->put($media->storage_path, 'x');
        $value = CoreValue::factory()->create();

        // Invalid/empty payloads: the point is authorization, not success.
        $uri = strtr($path, [':media' => (string) $media->id, ':value' => (string) $value->id]);

        return $this->from('/admin')->call($method, $uri, ['alt_text' => 'x']);
    }

    #[DataProvider('adminRoutes')]
    public function test_guest_is_redirected_to_login(string $method, string $path): void
    {
        $this->hit($method, $path)->assertRedirect(route('admin.login'));
    }

    #[DataProvider('adminRoutes')]
    public function test_allowed_roles_get_through(string $method, string $path, array $allowed, array $denied, int $status): void
    {
        foreach ($allowed as $role) {
            $this->actingAs(User::factory()->withRole($role)->create());

            $this->hit($method, $path)->assertStatus($status);
        }
    }

    /**
     * Routes that some roles may not use (dashboard and own password are
     * open to every signed-in CMS user).
     *
     * @return array<string, array{string, string, list<string>, list<string>, int}>
     */
    public static function restrictedRoutes(): array
    {
        return array_filter(self::adminRoutes(), fn (array $route) => $route[3] !== []);
    }

    #[DataProvider('restrictedRoutes')]
    public function test_other_roles_get_403(string $method, string $path, array $allowed, array $denied): void
    {
        foreach ($denied as $role) {
            $this->actingAs(User::factory()->withRole($role)->create());

            $this->hit($method, $path)->assertForbidden();
        }
    }

    public function test_every_admin_route_is_covered_and_protected(): void
    {
        $covered = collect(self::adminRoutes())->map(fn ($r) => $r[0].' '.strtr($r[1], self::PLACEHOLDERS))->sort()->values()->all();

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'admin') && ! in_array($route->uri(), ['admin/login', 'admin/logout', 'admin/forgot-password', 'admin/reset-password', 'admin/reset-password/{token}'], true));

        $this->assertSame($covered, $routes->map(fn ($r) => $r->methods()[0].' /'.$r->uri())->sort()->values()->all());

        foreach ($routes as $route) {
            $this->assertContains('auth', $route->gatherMiddleware(), $route->uri());
            $this->assertContains('active', $route->gatherMiddleware(), $route->uri());
        }
    }

    /**
     * A directory in public/ named like a route's first segment is served by
     * the web server instead of the app (e.g. public/admin shadowing /admin).
     */
    public function test_no_public_directory_shadows_a_route(): void
    {
        $segments = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => explode('/', $route->uri())[0])
            // "storage": Laravel's own file route, which the public/storage
            // link is meant to take over.
            ->reject(fn ($segment) => $segment === '' || $segment === 'storage' || str_starts_with($segment, '{'))
            ->unique();

        foreach ($segments as $segment) {
            $this->assertDirectoryDoesNotExist(public_path($segment), "public/{$segment} would shadow the /{$segment} routes");
        }
    }

    public function test_no_routes_for_removed_modules_remain(): void
    {
        foreach (['admin.services.index', 'admin.team.index', 'admin.projects.index', 'admin.blog.index', 'admin.pricing.index'] as $name) {
            $this->assertFalse(Route::has($name), $name);
        }
    }

    public function test_admin_views_never_output_unescaped_html(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views/admin')));

        foreach ($files as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $this->assertStringNotContainsString('{!!', file_get_contents($file->getPathname()), $file->getPathname());
            }
        }
    }

    public function test_sidebar_shows_only_permitted_items(): void
    {
        $this->actingAs(User::factory()->withRole(self::NONE)->create());
        $this->get('/admin')->assertOk()->assertDontSee(route('admin.media.index'), false);

        $this->actingAs(User::factory()->withRole('author')->create());
        $html = $this->get('/admin')->assertSee('href="'.route('admin.media.index').'"', false)->getContent();
        $this->assertMatchesRegularExpression('~<a href="'.preg_quote(route('admin.dashboard'), '~').'"\s+aria-current="page"\s*>Dashboard</a>~', $html);
    }
}
