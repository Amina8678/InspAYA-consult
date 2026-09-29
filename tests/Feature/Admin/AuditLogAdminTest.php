<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AuditLogController;
use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\ContactSubmission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuditLogAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function as(string $role): User
    {
        $user = User::factory()->withRole($role)->create();
        $this->actingAs($user);

        return $user;
    }

    // Access -------------------------------------------------------------------

    public function test_only_super_admin_and_administrator_reach_the_audit_log(): void
    {
        foreach (['editor', 'author'] as $role) {
            $this->as($role);

            $this->get(route('admin.audit-logs.index'))->assertForbidden();
            $this->get('/admin')->assertDontSee(route('admin.audit-logs.index'), false);
        }

        foreach (['super-admin', 'administrator'] as $role) {
            $this->as($role);
            $log = AuditLog::factory()->create();

            $this->get(route('admin.audit-logs.index'))->assertOk();
            $this->get(route('admin.audit-logs.show', $log))->assertOk();
        }
    }

    // No edit or delete path (D9) ----------------------------------------------

    public function test_there_is_no_edit_or_delete_route_for_audit_logs(): void
    {
        $this->as('super-admin');
        $log = AuditLog::factory()->create();

        $html = $this->get(route('admin.audit-logs.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('>Edit<', $html);
        $this->assertStringNotContainsString('>Delete<', $html);

        $show = $this->get(route('admin.audit-logs.show', $log))->assertOk()->getContent();
        $this->assertStringNotContainsString('>Edit<', $show);
        $this->assertStringNotContainsString('>Delete<', $show);

        // Proven exhaustively by AdminRoutesTest::test_every_admin_route_is_covered_and_protected too.
        foreach (['create', 'store', 'edit', 'update', 'delete', 'destroy'] as $action) {
            $this->assertFalse(Route::has("admin.audit-logs.{$action}"), "admin.audit-logs.{$action}");
        }

        // The model itself refuses at a layer below any route.
        $this->expectException(\LogicException::class);
        $log->delete();
    }

    // Filtering and pagination ---------------------------------------------

    public function test_list_is_filterable_by_entity_type_action_actor_and_date_range(): void
    {
        $admin = $this->as('administrator');
        $post = BlogPost::factory()->create();
        AuditLog::factory()->forEntity($post, 'published')->create(['user_id' => $admin->id, 'created_at' => '2026-01-10']);
        AuditLog::factory()->anonymous('login_throttled')->create(['created_at' => '2026-01-15']);
        AuditLog::factory()->create(['action' => 'login', 'created_at' => '2026-02-01']);

        $this->get(route('admin.audit-logs.index', ['entity_type' => 'blog_post']))->assertOk()
            ->assertViewHas('logs', fn ($p) => $p->total() === 1);

        $this->get(route('admin.audit-logs.index', ['action' => 'published']))->assertOk()
            ->assertViewHas('logs', fn ($p) => $p->total() === 1);

        $this->get(route('admin.audit-logs.index', ['actor' => (string) $admin->id]))->assertOk()
            ->assertViewHas('logs', fn ($p) => $p->total() === 1);

        $this->get(route('admin.audit-logs.index', ['actor' => 'guest']))->assertOk()
            ->assertViewHas('logs', fn ($p) => $p->total() === 1);

        $this->get(route('admin.audit-logs.index', ['from' => '2026-01-01', 'to' => '2026-01-31']))->assertOk()
            ->assertViewHas('logs', fn ($p) => $p->total() === 2);

        $this->get(route('admin.audit-logs.index'))->assertOk()
            ->assertViewHas('logs', fn ($p) => $p->total() === 3);
    }

    public function test_list_is_paginated(): void
    {
        $this->as('super-admin');
        AuditLog::factory()->count(AuditLogController::PER_PAGE + 5)->create();

        $this->get(route('admin.audit-logs.index'))->assertOk()
            ->assertViewHas('logs', fn ($p) => $p->count() === AuditLogController::PER_PAGE && $p->total() === AuditLogController::PER_PAGE + 5);
    }

    // Performance ----------------------------------------------------------

    public function test_query_count_does_not_grow_with_more_log_rows(): void
    {
        $this->as('super-admin');
        $post = BlogPost::factory()->create();
        AuditLog::factory()->forEntity($post, 'updated')->create();
        AuditLog::factory()->create();

        $count = function () {
            $n = 0;
            DB::listen(function () use (&$n) {
                $n++;
            });
            $this->get(route('admin.audit-logs.index'))->assertOk();
            DB::getEventDispatcher()->forget('Illuminate\Database\Events\QueryExecuted');

            return $n;
        };
        // Warms the acting user's cached role/permissions (User::permissionSlugs()
        // caches per instance): otherwise the first measurement alone pays for
        // it, which isn't the N+1 behaviour under test.
        $this->get(route('admin.audit-logs.index'))->assertOk();
        $few = $count();

        // More rows of the same entity types already on the page: the
        // per-type morphTo eager load must not scale with row count.
        AuditLog::factory()->forEntity($post, 'updated')->count(10)->create();
        AuditLog::factory()->count(10)->create();

        $this->assertSame($few, $count());
    }

    // Rendering: diff vs metadata-only, and escaping ----------------------

    public function test_detail_page_renders_a_before_after_diff_readably_and_escapes_stored_values(): void
    {
        $this->as('super-admin');
        $actor = User::factory()->withRole('administrator')->create(['name' => 'Diff Admin']);
        $log = AuditLog::factory()->create([
            'user_id' => $actor->id,
            'action' => 'updated',
            'old_values' => ['title' => '<script>alert(1)</script> Old title'],
            'new_values' => ['title' => 'New title'],
        ]);

        $html = $this->get(route('admin.audit-logs.show', $log))->assertOk()->getContent();
        $this->assertStringContainsString('Diff Admin', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('New title', $html);
    }

    public function test_detail_page_shows_metadata_only_entries_without_a_diff(): void
    {
        $this->as('super-admin');
        $log = AuditLog::factory()->anonymous('login_failed')->create(['new_values' => ['email' => 'someone@example.com', 'reason' => 'invalid_password']]);

        $this->get(route('admin.audit-logs.show', $log))->assertOk()
            ->assertSee('Guest')
            ->assertSee('someone@example.com')
            ->assertSee('invalid_password');
    }

    public function test_an_entry_with_nothing_recorded_says_so_instead_of_an_empty_table(): void
    {
        $this->as('super-admin');
        $log = AuditLog::factory()->create(['old_values' => null, 'new_values' => null]);

        $this->get(route('admin.audit-logs.show', $log))->assertOk()
            ->assertSee('No additional details were recorded for this entry.');
    }

    // Links to the live entity, and the deleted-entity fallback -----------

    public function test_the_list_links_to_the_entity_when_it_still_exists(): void
    {
        $this->as('super-admin');
        $post = BlogPost::factory()->create(['title' => 'Still Here']);
        AuditLog::factory()->forEntity($post, 'updated')->create();

        $this->get(route('admin.audit-logs.index'))->assertOk()
            ->assertSee('href="'.route('admin.posts.edit', $post).'"', false)
            ->assertSee('Still Here');
    }

    public function test_a_deleted_entity_shows_a_fallback_label_and_no_link(): void
    {
        $this->as('super-admin');
        $post = BlogPost::factory()->create(['title' => 'Going Away']);
        $log = AuditLog::factory()->forEntity($post, 'created')->create(['old_values' => null, 'new_values' => ['title' => 'Going Away']]);
        $post->delete();

        // The list query deliberately excludes old_values/new_values for
        // performance (item 4), so a deleted entity there falls back to a
        // bare identifier, not the name from the stored diff.
        $indexHtml = $this->get(route('admin.audit-logs.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Blog post #'.$post->id.' (deleted)', $indexHtml);
        $this->assertStringNotContainsString(route('admin.posts.edit', $post->id), $indexHtml);

        // The detail page loads the full row, so it can still show the name.
        $showHtml = $this->get(route('admin.audit-logs.show', $log))->assertOk()->getContent();
        $this->assertStringContainsString('Going Away', $showHtml);
        $this->assertStringContainsString('(deleted)', $showHtml);
        $this->assertStringNotContainsString(route('admin.posts.edit', $post->id), $showHtml);
    }

    // Recent actions from previous stages, end to end -----------------------

    public function test_recent_actions_from_previous_stages_all_appear_correctly(): void
    {
        // A login.
        $admin = User::factory()->withRole('administrator')->create(['email' => 'audit-admin@example.test']);
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        // A blog post publish (content fields kept identical, so this is a
        // clean status-only transition: one `published` entry, not also an
        // incidental `updated` for a changed excerpt/content).
        $this->actingAs($admin);
        $post = BlogPost::factory()->inReview()->create(['title' => 'Published Via Audit Test']);
        $this->put(route('admin.posts.update', $post), [
            'title' => $post->title, 'slug' => $post->slug, 'excerpt' => $post->excerpt, 'content' => $post->content,
            'category_id' => '', 'status' => 'published',
        ])->assertSessionHasNoErrors();

        // An enquiry status change.
        $submission = ContactSubmission::factory()->create();
        $this->put(route('admin.enquiries.update', $submission), ['status' => 'in_progress'])->assertSessionHasNoErrors();

        // A user deactivation (also produces a paired `updated` entry for
        // the same field, same as the blog post's `published` — both are
        // checked for directly rather than asserting an exact full sequence).
        $target = User::factory()->withRole('editor')->create(['name' => 'Deactivate Me']);
        $this->put(route('admin.users.update', $target), [
            'name' => $target->name, 'email' => $target->email, 'username' => $target->username,
            'role_id' => (string) $target->role_id, 'status' => 'inactive',
        ])->assertSessionHasNoErrors();

        foreach (['login', 'published', 'updated', 'deactivated'] as $expectedAction) {
            $this->assertTrue(
                AuditLog::where('action', $expectedAction)->exists(),
                "Expected an audit entry for action \"{$expectedAction}\".",
            );
        }
        $this->assertSame(1, AuditLog::where('action', 'published')->count());
        $this->assertSame(1, AuditLog::where('action', 'deactivated')->count());

        $html = $this->get(route('admin.audit-logs.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Login', $html);
        $this->assertStringContainsString('Published', $html);
        $this->assertStringContainsString('Deactivated', $html);
        $this->assertStringContainsString('Published Via Audit Test', $html);
        $this->assertStringContainsString('Deactivate Me', $html);
        // The enquiry entry never shows the visitor's own name/subject (stage 7).
        $this->assertStringContainsString('Enquiry #'.$submission->id, $html);
    }
}
