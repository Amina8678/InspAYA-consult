<?php

namespace Tests\Feature\Auth;

use App\Models\BlogPost;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Proves the admin authorization layer end to end on a test-only route
 * group (the real admin controllers are rewritten in a later stage):
 * guest → 302 to login, wrong role → 403, allowed → 200.
 */
class AdminRouteLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        View::getFinder()->prependLocation(base_path('tests/Fixtures/views'));

        // Same middleware stack as the real admin group in routes/web.php.
        Route::middleware(['web', 'auth', 'auth.session', 'active'])->prefix('__test/admin')->group(function () {
            Route::get('/settings', fn () => view('admin-test.page', ['label' => 'settings']))
                ->middleware('permission:settings.manage');
            Route::get('/users', fn () => view('admin-test.page', ['label' => 'users']))
                ->middleware('permission:users.view,users.update');
            Route::get('/posts/{post}/edit', fn (BlogPost $post) => view('admin-test.page', ['label' => $post->title]))
                ->middleware('can:update,post');
            Route::post('/posts/{post}/publish', fn (BlogPost $post) => response()->noContent())
                ->middleware('can:publish,post');
        });
    }

    private function as(string $role): User
    {
        return User::factory()->withRole($role)->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/__test/admin/settings')->assertRedirect(route('admin.login'));
    }

    public function test_wrong_role_gets_403(): void
    {
        $this->actingAs($this->as('editor'))->get('/__test/admin/settings')->assertForbidden();
    }

    public function test_allowed_role_gets_200(): void
    {
        $this->actingAs($this->as('administrator'))->get('/__test/admin/settings')
            ->assertOk()
            ->assertViewIs('admin-test.page')
            ->assertSee('stub:admin-test|settings');
    }

    public function test_multiple_permissions_are_all_required(): void
    {
        $this->actingAs($this->as('administrator'))->get('/__test/admin/users')->assertOk();
        $this->actingAs($this->as('author'))->get('/__test/admin/users')->assertForbidden();
    }

    public function test_inactive_user_is_signed_out_before_authorization(): void
    {
        $this->actingAs($this->as('super-admin')->forceFill(['status' => 'inactive']))
            ->get('/__test/admin/settings')
            ->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_author_reaches_only_their_own_post(): void
    {
        $author = $this->as('author');
        $own = BlogPost::factory()->for($author, 'author')->create(['title' => 'Mine']);
        $other = BlogPost::factory()->create();

        $this->actingAs($author)->get("/__test/admin/posts/{$own->id}/edit")->assertOk()->assertSee('Mine');
        $this->actingAs($author)->get("/__test/admin/posts/{$other->id}/edit")->assertForbidden();
    }

    public function test_editor_cannot_publish_but_administrator_can(): void
    {
        $post = BlogPost::factory()->inReview()->create();

        $this->actingAs($this->as('editor'))->post("/__test/admin/posts/{$post->id}/publish")->assertForbidden();
        $this->actingAs($this->as('administrator'))->post("/__test/admin/posts/{$post->id}/publish")->assertNoContent();
    }
}
