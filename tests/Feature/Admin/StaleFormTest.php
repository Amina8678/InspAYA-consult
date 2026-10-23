<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\PendingRevision;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Edit forms carry the version they were loaded with. Publishers are refused
 * when it has moved; Editors' revisions store it as their baseline.
 */
class StaleFormTest extends TestCase
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

    private function pagePayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Our approach', 'slug' => '', 'meta_title' => '', 'meta_description' => '', 'canonical_url' => '', 'og_image_id' => '',
            'sections' => [['type' => 'text', 'heading' => 'How we work', 'body' => 'Body copy.']],
        ];
    }

    private function postPayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Board effectiveness', 'slug' => '', 'excerpt' => 'Summary.', 'content' => 'Content.',
            'category_id' => '', 'meta_title' => '', 'meta_description' => '', 'canonical_url' => '',
            'featured_image_id' => '', 'published_at' => '',
        ];
    }

    // The forms carry the version ------------------------------------------------------

    public function test_the_edit_forms_carry_the_loaded_version_in_a_hidden_field(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about']);
        $post = BlogPost::factory()->published()->create();
        $page->update(['title' => 'Bumped']);
        $this->as('administrator');

        $this->get(route('admin.pages.edit', $page))->assertSee('<input type="hidden" name="version" value="2">', false);
        $this->get(route('admin.posts.edit', $post))->assertSee('<input type="hidden" name="version" value="1">', false);
        $this->get(route('admin.pages.create'))->assertDontSee('name="version"', false);
        $this->get(route('admin.posts.create'))->assertDontSee('name="version"', false);
    }

    public function test_adding_a_section_keeps_the_version_the_form_was_loaded_with(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about']);
        $this->as('administrator');

        $this->followingRedirects()->from(route('admin.pages.edit', $page))
            ->put(route('admin.pages.update', $page), $this->pagePayload(['version' => 1, 'section_action' => 'add', 'new_section_type' => 'text']))
            ->assertSee('<input type="hidden" name="version" value="1">', false);
    }

    // Two publishers ---------------------------------------------------------------------

    public function test_the_second_of_two_publishers_is_refused_and_keeps_what_they_typed(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $first = $this->as('administrator');
        $second = User::factory()->withRole('super-admin')->create();

        // Both open the page at version 1; the first saves.
        $this->put(route('admin.pages.update', $page), $this->pagePayload(['title' => 'First wins', 'version' => 1]))->assertSessionHasNoErrors();
        $this->assertSame(2, $page->fresh()->version);

        $this->actingAs($second);
        $response = $this->followingRedirects()->from(route('admin.pages.edit', $page))
            ->put(route('admin.pages.update', $page), $this->pagePayload(['title' => 'Second loses', 'version' => 1]));

        $response->assertSee('was changed by someone else since you opened it')
            ->assertSee('value="Second loses"', false)
            ->assertSee('Open the current version in a new tab');
        $this->assertSame('First wins', $page->fresh()->title, 'Nothing of the second save was written.');
        $this->assertSame(2, $page->fresh()->version);
        $this->assertSame(0, AuditLog::where('user_id', $second->id)->where('action', 'updated')->count());
    }

    public function test_after_reviewing_the_current_version_a_save_goes_through(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about']);
        $this->as('administrator');
        $this->put(route('admin.pages.update', $page), $this->pagePayload(['title' => 'First', 'version' => 1]));

        $this->put(route('admin.pages.update', $page), $this->pagePayload(['title' => 'Second, after reviewing', 'version' => 2]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Second, after reviewing', $page->fresh()->title);
        $this->assertSame(3, $page->fresh()->version);
    }

    public function test_the_same_applies_to_posts_and_a_stale_post_is_not_saved(): void
    {
        $post = BlogPost::factory()->published()->create(['title' => 'Original']);
        $this->as('administrator');
        $this->put(route('admin.posts.update', $post), $this->postPayload(['title' => 'First', 'version' => 1]))->assertSessionHasNoErrors();

        $this->put(route('admin.posts.update', $post), $this->postPayload(['title' => 'Second', 'version' => 1]))
            ->assertSessionHasErrors('version');

        $this->assertSame('First', $post->fresh()->title);
    }

    public function test_a_publishers_form_without_a_version_is_still_accepted(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about']);
        $this->as('administrator');

        $this->put(route('admin.pages.update', $page), $this->pagePayload(['title' => 'No version sent']))->assertSessionHasNoErrors();
        $this->assertSame('No version sent', $page->fresh()->title);
    }

    public function test_a_garbage_version_is_a_validation_error_not_a_500(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about']);
        $this->as('administrator');

        $this->put(route('admin.pages.update', $page), $this->pagePayload(['version' => 'abc']))->assertSessionHasErrors('version');
    }

    // Editors: the baseline is the form's version ------------------------------------------

    public function test_an_editors_revision_stores_the_version_their_form_was_loaded_with(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $admin = User::factory()->withRole('administrator')->create();

        // The Editor opens the form at version 1; an Administrator then saves; only then does the Editor submit.
        $page->update(['title' => 'Changed by an admin']);
        $this->assertSame(2, $page->fresh()->version);

        $this->as('editor');
        $this->put(route('admin.pages.update', $page), $this->pagePayload(['title' => 'Editor title', 'version' => 1]))->assertSessionHasNoErrors();

        $revision = PendingRevision::sole();
        $this->assertSame(1, $revision->base_version, 'The form\'s version, not the version read at submit time.');
        $this->assertTrue($revision->liveRecordChanged());

        $this->actingAs($admin);
        $this->get(route('admin.content-approvals.show', $revision))->assertSee('changed since the edit was submitted');
        $this->post(route('admin.content-approvals.approve', $revision))->assertSessionHasErrors('approve');
        $this->assertSame('Changed by an admin', $page->fresh()->title);

        $this->post(route('admin.content-approvals.approve', $revision), ['confirm_live_change' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('Editor title', $page->fresh()->title);
        $this->assertTrue(AuditLog::firstWhere('action', 'revision_approved')->new_values['approved_after_live_change']);
    }

    public function test_an_editor_whose_form_was_current_is_not_flagged(): void
    {
        $post = BlogPost::factory()->published()->create();
        $post->update(['excerpt' => 'Edited earlier']);
        $this->as('editor');

        $this->put(route('admin.posts.update', $post), $this->postPayload(['title' => 'Editor post', 'version' => 2]))->assertSessionHasNoErrors();

        $revision = PendingRevision::sole();
        $this->assertSame(2, $revision->base_version);
        $this->assertFalse($revision->liveRecordChanged());
    }

    public function test_an_editor_submission_without_a_version_falls_back_to_the_live_version(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about']);
        $page->update(['title' => 'v2']);
        $this->as('editor');

        $this->put(route('admin.pages.update', $page), $this->pagePayload(['title' => 'No version']))->assertSessionHasNoErrors();

        $this->assertSame(2, PendingRevision::sole()->base_version);
    }

    public function test_a_second_submission_replaces_the_baseline_with_its_own_form_version(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about']);
        $this->as('editor');
        $this->put(route('admin.pages.update', $page), $this->pagePayload(['title' => 'First try', 'version' => 1]));
        $page->update(['title' => 'Admin edit']);

        $this->put(route('admin.pages.update', $page), $this->pagePayload(['title' => 'Second try', 'version' => 2]));

        $this->assertSame(1, PendingRevision::count());
        $this->assertSame(2, PendingRevision::sole()->base_version);
        $this->assertFalse(PendingRevision::sole()->liveRecordChanged());
    }

    // What the old updated_at check missed --------------------------------------------------

    public function test_an_edit_in_the_very_same_second_is_now_detected(): void
    {
        Carbon::setTestNow('2026-10-05 12:00:00');
        $page = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $editor = User::factory()->withRole('editor')->create();
        $revision = PendingRevision::factory()->create([
            'revisable_type' => $page->getMorphClass(), 'revisable_id' => $page->id, 'submitted_by' => $editor->id,
            'changes' => ['title' => 'Editor title'], 'base_version' => $page->version,
        ]);
        $stampBefore = $page->fresh()->updated_at;

        $page->update(['title' => 'Edited in the same second']);

        $this->assertTrue($page->fresh()->updated_at->equalTo($stampBefore), 'updated_at cannot tell these two states apart.');
        $this->assertTrue($revision->fresh()->liveRecordChanged());

        $this->as('administrator');
        $this->post(route('admin.content-approvals.approve', $revision))->assertSessionHasErrors('approve');
        $this->assertSame('Edited in the same second', $page->fresh()->title);
    }

    public function test_legacy_revisions_without_a_baseline_are_never_stale(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about']);
        $editor = User::factory()->withRole('editor')->create();
        $revision = PendingRevision::factory()->create([
            'revisable_type' => $page->getMorphClass(), 'revisable_id' => $page->id, 'submitted_by' => $editor->id,
            'changes' => ['title' => 'Legacy'], 'base_version' => null,
        ]);
        $page->update(['title' => 'Edited since']);
        $this->as('administrator');

        $this->assertFalse($revision->fresh()->liveRecordChanged());
        $this->post(route('admin.content-approvals.approve', $revision))->assertSessionHasNoErrors();
        $this->assertSame('Legacy', $page->fresh()->title);
    }
}
