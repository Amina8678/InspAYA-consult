<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Consultant;
use App\Models\ContactSubmission;
use App\Models\ContactSubmissionNote;
use App\Models\CoreValue;
use App\Models\Media;
use App\Models\Page;
use App\Models\Role;
use App\Models\Service;
use App\Models\Tag;
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
            'pages list' => ['GET', '/admin/pages', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'page create page' => ['GET', '/admin/pages/create', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'page store' => ['POST', '/admin/pages', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'page edit' => ['GET', '/admin/pages/:page/edit', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'page update' => ['PUT', '/admin/pages/:page', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'page delete confirm' => ['GET', '/admin/pages/:page/delete', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'page destroy' => ['DELETE', '/admin/pages/:page', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'services list' => ['GET', '/admin/services', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'service create page' => ['GET', '/admin/services/create', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'service store' => ['POST', '/admin/services', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'service edit' => ['GET', '/admin/services/:service/edit', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'service update' => ['PUT', '/admin/services/:service', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'service move' => ['POST', '/admin/services/:service/move', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'service delete confirm' => ['GET', '/admin/services/:service/delete', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'service destroy' => ['DELETE', '/admin/services/:service', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'consultants list' => ['GET', '/admin/consultants', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'consultant create page' => ['GET', '/admin/consultants/create', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'consultant store' => ['POST', '/admin/consultants', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'consultant edit' => ['GET', '/admin/consultants/:consultant/edit', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'consultant update' => ['PUT', '/admin/consultants/:consultant', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'consultant move' => ['POST', '/admin/consultants/:consultant/move', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'consultant delete confirm' => ['GET', '/admin/consultants/:consultant/delete', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'consultant destroy' => ['DELETE', '/admin/consultants/:consultant', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'core values list' => ['GET', '/admin/core-values', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'core value create page' => ['GET', '/admin/core-values/create', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'core value store' => ['POST', '/admin/core-values', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'core value edit' => ['GET', '/admin/core-values/:value/edit', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'core value update' => ['PUT', '/admin/core-values/:value', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'core value move' => ['POST', '/admin/core-values/:value/move', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'core value delete confirm' => ['GET', '/admin/core-values/:value/delete', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'core value destroy' => ['DELETE', '/admin/core-values/:value', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            // Posts: everyone who writes posts can list and create (all four
            // seeded roles have posts.create); editing/deleting a post nobody
            // here owns needs posts.edit-any/posts.delete, so Authors (own
            // drafts only, never delete — D11) are denied at this generic,
            // ownership-blind level (same pattern as media edit above).
            'posts list' => ['GET', '/admin/posts', $everyone, [self::NONE], 200],
            'post create page' => ['GET', '/admin/posts/create', $everyone, [self::NONE], 200],
            'post store' => ['POST', '/admin/posts', $everyone, [self::NONE], 302],
            'post edit' => ['GET', '/admin/posts/:post/edit', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'post update' => ['PUT', '/admin/posts/:post', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'post delete confirm' => ['GET', '/admin/posts/:post/delete', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'post destroy' => ['DELETE', '/admin/posts/:post', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'categories list' => ['GET', '/admin/categories', $everyone, [self::NONE], 200],
            'category create page' => ['GET', '/admin/categories/create', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'category store' => ['POST', '/admin/categories', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'category edit' => ['GET', '/admin/categories/:category/edit', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'category update' => ['PUT', '/admin/categories/:category', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'category delete confirm' => ['GET', '/admin/categories/:category/delete', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'category destroy' => ['DELETE', '/admin/categories/:category', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'tags list' => ['GET', '/admin/tags', $everyone, [self::NONE], 200],
            'tag create page' => ['GET', '/admin/tags/create', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'tag store' => ['POST', '/admin/tags', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'tag edit' => ['GET', '/admin/tags/:tag/edit', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'tag update' => ['PUT', '/admin/tags/:tag', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'tag delete confirm' => ['GET', '/admin/tags/:tag/delete', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'tag destroy' => ['DELETE', '/admin/tags/:tag', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'enquiries list' => ['GET', '/admin/enquiries', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'enquiry export' => ['GET', '/admin/enquiries/export', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'enquiry show' => ['GET', '/admin/enquiries/:submission', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 200],
            'enquiry update' => ['PUT', '/admin/enquiries/:submission', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            'enquiry delete confirm' => ['GET', '/admin/enquiries/:submission/delete', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'enquiry destroy' => ['DELETE', '/admin/enquiries/:submission', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'enquiry note store' => ['POST', '/admin/enquiries/:submission/notes', ['super-admin', 'administrator', 'editor'], ['author', self::NONE], 302],
            // Editing/updating a note nobody here authored is refused for
            // every role, including Super Admin: ContactSubmissionNotePolicy
            // requires authorship with no admin override (plan §6, item 4).
            'enquiry note edit' => ['GET', '/admin/enquiries/:submission/notes/:note/edit', [], ['super-admin', 'administrator', 'editor', 'author', self::NONE], 200],
            'enquiry note update' => ['PUT', '/admin/enquiries/:submission/notes/:note', [], ['super-admin', 'administrator', 'editor', 'author', self::NONE], 302],
            'enquiry note delete confirm' => ['GET', '/admin/enquiries/:submission/notes/:note/delete', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'enquiry note destroy' => ['DELETE', '/admin/enquiries/:submission/notes/:note', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'users list' => ['GET', '/admin/users', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'user create page' => ['GET', '/admin/users/create', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'user store' => ['POST', '/admin/users', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'user edit' => ['GET', '/admin/users/:user/edit', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'user update' => ['PUT', '/admin/users/:user', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'user reset password' => ['POST', '/admin/users/:user/reset-password', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 302],
            'audit logs list' => ['GET', '/admin/audit-logs', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
            'audit log show' => ['GET', '/admin/audit-logs/:auditLog', ['super-admin', 'administrator'], ['editor', 'author', self::NONE], 200],
        ];
    }

    /** Route placeholder => route parameter as registered. */
    private const PLACEHOLDERS = [
        ':media' => '{media}', ':value' => '{coreValue}', ':service' => '{service}', ':consultant' => '{consultant}', ':page' => '{page}',
        ':post' => '{post}', ':category' => '{category}', ':tag' => '{tag}', ':submission' => '{submission}', ':note' => '{note}',
        ':user' => '{user}', ':auditLog' => '{auditLog}',
    ];

    private function hit(string $method, string $path)
    {
        $media = Media::factory()->create(['alt_text' => 'x']);
        Storage::disk('public')->put($media->storage_path, 'x');
        $value = CoreValue::factory()->create();
        $service = Service::factory()->create();
        $consultant = Consultant::factory()->create();
        $page = Page::factory()->create();
        $post = BlogPost::factory()->create();
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();
        $submission = ContactSubmission::factory()->create();
        $note = ContactSubmissionNote::factory()->for($submission, 'submission')->create();
        // Not a Super Admin, so any allowed role here (super-admin,
        // administrator) generically passes the target-acting-on checks.
        $targetUser = User::factory()->create();
        $auditLog = AuditLog::factory()->create();

        // Invalid/empty payloads: the point is authorization, not success.
        $uri = strtr($path, [
            ':media' => (string) $media->id, ':value' => (string) $value->id,
            ':service' => (string) $service->id, ':consultant' => (string) $consultant->id,
            ':page' => (string) $page->id, ':post' => (string) $post->id,
            ':category' => (string) $category->id, ':tag' => (string) $tag->id,
            ':submission' => (string) $submission->id, ':note' => (string) $note->id,
            ':user' => (string) $targetUser->id, ':auditLog' => (string) $auditLog->id,
        ]);

        return $this->from('/admin')->call($method, $uri, ['alt_text' => 'x']);
    }

    #[DataProvider('adminRoutes')]
    public function test_guest_is_redirected_to_login(string $method, string $path, array $allowed, array $denied, int $status): void
    {
        $this->hit($method, $path)->assertRedirect(route('admin.login'));
    }

    #[DataProvider('adminRoutes')]
    public function test_allowed_roles_get_through(string $method, string $path, array $allowed, array $denied, int $status): void
    {
        // A route can be ownership-gated for every role (e.g. editing
        // someone else's enquiry note): nothing here generically passes it,
        // and that is itself the behaviour under test.
        if ($allowed === []) {
            $this->assertSame([], $allowed);

            return;
        }

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
    public function test_other_roles_get_403(string $method, string $path, array $allowed, array $denied, int $status): void
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
        foreach (['admin.team.index', 'admin.projects.index', 'admin.blog.index', 'admin.pricing.index'] as $name) {
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
