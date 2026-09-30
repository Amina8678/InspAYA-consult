<?php

namespace Tests\Feature\Admin;

use App\Enums\PendingRevisionStatus;
use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Page;
use App\Models\PendingRevision;
use App\Models\Tag;
use App\Models\User;
use App\Support\ContentApproval;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Content approval workflow (stage 16): an already-live page or post edited
 * by someone without content.publish is never changed directly — the edit
 * becomes a pending revision for an Administrator/Super Admin to apply or
 * discard.
 */
class ContentApprovalTest extends TestCase
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

    private function publicPage(string $route, mixed $param = null)
    {
        $this->app->forgetScopedInstances();

        return $this->get($param === null ? route($route) : route($route, $param));
    }

    /**
     * @return array<string, mixed>
     */
    private function pagePayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Our approach', 'slug' => '', 'meta_title' => '', 'meta_description' => '', 'canonical_url' => '', 'og_image_id' => '',
            'sections' => [['type' => 'text', 'heading' => 'How we work', 'body' => 'Updated body copy.']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postPayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Board effectiveness in 2026', 'slug' => '', 'excerpt' => 'A short summary.',
            'content' => 'Updated content.', 'category_id' => '', 'meta_title' => '', 'meta_description' => '',
            'canonical_url' => '', 'featured_image_id' => '', 'published_at' => '',
        ];
    }

    // Live page: an Editor's edit is deferred, the public site is untouched --

    public function test_an_editors_edit_to_a_live_page_does_not_change_the_public_site_until_approved(): void
    {
        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);

        $editor = $this->as('editor');
        $this->put(route('admin.pages.update', $about), $this->pagePayload(['title' => 'About us (new)']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Submitted your changes to "About us" for approval.');

        // The live record — and so the public page — is completely untouched.
        $this->assertSame('About us', $about->fresh()->title);
        $this->publicPage('about')->assertOk()->assertSee('About us')->assertDontSee('About us (new)');

        $revision = PendingRevision::sole();
        $this->assertSame((new Page)->getMorphClass(), $revision->revisable_type);
        $this->assertSame($about->id, $revision->revisable_id);
        $this->assertSame($editor->id, $revision->submitted_by);
        $this->assertSame(PendingRevisionStatus::Pending, $revision->status);
        $this->assertSame('About us (new)', $revision->changes['title']);

        $log = AuditLog::firstWhere('action', 'revision_submitted');
        $this->assertSame($editor->id, $log->user_id);
        $this->assertSame((new Page)->getMorphClass(), $log->entity_type);
        $this->assertSame($about->id, $log->entity_id);
        $this->assertSame('About us', $log->old_values['title']);
        $this->assertSame('About us (new)', $log->new_values['title']);
    }

    // Live post: same rule, including tags -----------------------------------

    public function test_an_editors_edit_to_a_live_post_defers_content_and_tag_changes(): void
    {
        $post = BlogPost::factory()->published()->create(['title' => 'Old title']);
        $oldTag = Tag::factory()->create();
        $newTag = Tag::factory()->create();
        $post->tags()->sync([$oldTag->id]);

        $editor = $this->as('editor');
        $this->put(route('admin.posts.update', $post), $this->postPayload([
            'title' => 'New title', 'tags' => [(string) $newTag->id],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Old title', $post->fresh()->title);
        $this->assertSame([$oldTag->id], $post->fresh()->tags()->pluck('tags.id')->all());
        $this->publicPage('insights.show', $post->slug)->assertOk()->assertSee('Old title');

        $revision = PendingRevision::sole();
        $this->assertSame('New title', $revision->changes['title']);
        $this->assertSame([$newTag->id], $revision->changes['tags']);
    }

    // Unpublished content is unaffected: Editors already fully control it ----

    public function test_editing_a_draft_or_in_review_post_still_applies_immediately(): void
    {
        $this->as('editor');
        $draft = BlogPost::factory()->draft()->create();
        $review = BlogPost::factory()->inReview()->create();

        $this->put(route('admin.posts.update', $draft), $this->postPayload(['title' => 'Edited draft']))->assertSessionHasNoErrors();
        $this->assertSame('Edited draft', $draft->fresh()->title);

        $this->put(route('admin.posts.update', $review), $this->postPayload(['title' => 'Edited in review']))->assertSessionHasNoErrors();
        $this->assertSame('Edited in review', $review->fresh()->title);

        $this->assertSame(0, PendingRevision::count());
    }

    // Publishers bypass the queue: they're the ones who'd approve it anyway --

    public function test_an_administrator_editing_a_live_page_applies_immediately(): void
    {
        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);

        $this->as('administrator');
        $this->put(route('admin.pages.update', $about), $this->pagePayload(['title' => 'About us (updated)']))
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Saved "About us (updated)".');

        $this->assertSame('About us (updated)', $about->fresh()->title);
        $this->assertSame(0, PendingRevision::count());
    }

    // The approval queue -------------------------------------------------------

    public function test_administrator_sees_the_pending_queue(): void
    {
        $editor = User::factory()->withRole('editor')->create(['name' => 'Pat Editor']);
        $page = Page::factory()->published()->create(['title' => 'About us']);
        PendingRevision::factory()->create([
            'revisable_type' => (new Page)->getMorphClass(), 'revisable_id' => $page->id,
            'submitted_by' => $editor->id, 'changes' => ['title' => 'About us, revised'],
        ]);

        $this->as('administrator');
        $this->get(route('admin.content-approvals.index'))->assertOk()
            ->assertSee('About us')
            ->assertSee('Pat Editor')
            ->assertSee('Page');
    }

    public function test_the_review_screen_diffs_the_proposed_change_against_the_live_record(): void
    {
        $category = Category::factory()->create(['name' => 'Governance']);
        $post = BlogPost::factory()->published()->create(['title' => 'Old title', 'category_id' => null]);
        $revision = PendingRevision::factory()->create([
            'revisable_type' => (new BlogPost)->getMorphClass(), 'revisable_id' => $post->id,
            'changes' => ['title' => 'New title', 'category_id' => $category->id],
        ]);

        $this->as('administrator');
        $this->get(route('admin.content-approvals.show', $revision))->assertOk()
            ->assertSeeInOrder(['Title', 'Old title', 'New title'])
            ->assertSee('Governance');
    }

    // Approving --------------------------------------------------------------

    public function test_approving_applies_the_change_and_is_audited(): void
    {
        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $editor = User::factory()->withRole('editor')->create();
        $revision = PendingRevision::factory()->create([
            'revisable_type' => (new Page)->getMorphClass(), 'revisable_id' => $about->id,
            'submitted_by' => $editor->id, 'changes' => ['title' => 'About us, revised'],
        ]);

        $admin = $this->as('administrator');
        $this->post(route('admin.content-approvals.approve', $revision))
            ->assertRedirect(route('admin.content-approvals.index'))
            ->assertSessionHas('status', 'Approved the change to "About us".');

        $this->assertSame('About us, revised', $about->fresh()->title);
        $this->publicPage('about')->assertOk()->assertSee('About us, revised');

        $this->assertSame(PendingRevisionStatus::Approved, $revision->fresh()->status);
        $this->assertSame($admin->id, $revision->fresh()->reviewed_by);
        $this->assertNotNull($revision->fresh()->reviewed_at);

        $log = AuditLog::firstWhere('action', 'revision_approved');
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('About us', $log->old_values['title']);
        $this->assertSame('About us, revised', $log->new_values['title']);
    }

    public function test_editors_and_authors_cannot_approve_or_reject(): void
    {
        $page = Page::factory()->published()->create();
        $revision = PendingRevision::factory()->create(['revisable_type' => (new Page)->getMorphClass(), 'revisable_id' => $page->id]);

        foreach (['editor', 'author'] as $role) {
            $this->as($role);
            $this->post(route('admin.content-approvals.approve', $revision))->assertForbidden();
            $this->post(route('admin.content-approvals.reject', $revision), ['reason' => 'No.'])->assertForbidden();
        }

        $this->assertSame(PendingRevisionStatus::Pending, $revision->fresh()->status);
    }

    // Rejecting ----------------------------------------------------------------

    public function test_rejecting_discards_the_change_and_is_visible_to_the_editor(): void
    {
        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $editor = User::factory()->withRole('editor')->create();
        $revision = PendingRevision::factory()->create([
            'revisable_type' => (new Page)->getMorphClass(), 'revisable_id' => $about->id,
            'submitted_by' => $editor->id, 'changes' => ['title' => 'About us, revised'],
        ]);

        $admin = $this->as('administrator');
        $this->post(route('admin.content-approvals.reject', $revision), ['reason' => 'Please confirm this with legal first.'])
            ->assertRedirect(route('admin.content-approvals.index'))
            ->assertSessionHas('status', 'Rejected the change to "About us".');

        // Discarded: the live record, and so the public site, never changed.
        $this->assertSame('About us', $about->fresh()->title);
        $this->publicPage('about')->assertOk()->assertDontSee('About us, revised');

        $revision->refresh();
        $this->assertSame(PendingRevisionStatus::Rejected, $revision->status);
        $this->assertSame($admin->id, $revision->reviewed_by);
        $this->assertSame('Please confirm this with legal first.', $revision->rejection_reason);

        $log = AuditLog::firstWhere('action', 'revision_rejected');
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Please confirm this with legal first.', $log->new_values['reason']);
        $this->assertSame('About us, revised', $log->new_values['rejected_changes']['title']);

        // Visible to the Editor: the dashboard alert and the edit-form banner.
        $this->actingAs($editor);
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Your edit to "About us" was rejected: Please confirm this with legal first.');
        $this->get(route('admin.pages.edit', $about))->assertOk()
            ->assertSee('Your last submitted change was rejected: Please confirm this with legal first.');
    }

    public function test_a_reason_is_required_to_reject(): void
    {
        $page = Page::factory()->published()->create();
        $revision = PendingRevision::factory()->create(['revisable_type' => (new Page)->getMorphClass(), 'revisable_id' => $page->id]);

        $this->as('administrator');
        $this->from(route('admin.content-approvals.show', $revision))
            ->post(route('admin.content-approvals.reject', $revision), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(PendingRevisionStatus::Pending, $revision->fresh()->status);
    }

    // Resubmission after rejection ---------------------------------------------

    public function test_resubmitting_after_a_rejection_creates_a_fresh_pending_entry_and_clears_the_alert(): void
    {
        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $editor = $this->as('editor');

        $this->put(route('admin.pages.update', $about), $this->pagePayload(['title' => 'About us v1']));
        $rejected = PendingRevision::sole();
        $this->actingAs(User::factory()->withRole('administrator')->create());
        $this->post(route('admin.content-approvals.reject', $rejected), ['reason' => 'Needs more work.']);

        $this->actingAs($editor);
        $this->put(route('admin.pages.update', $about), $this->pagePayload(['title' => 'About us v2']))
            ->assertSessionHasNoErrors();

        // A new row, not a reuse of the rejected one — full history is kept.
        $this->assertSame(2, PendingRevision::count());
        $fresh = PendingRevision::where('status', PendingRevisionStatus::Pending)->sole();
        $this->assertSame('About us v2', $fresh->changes['title']);

        // The rejection alert is superseded by the new pending one.
        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Your edit to &quot;About us&quot; is awaiting approval.', $html);
        $this->assertStringNotContainsString('was rejected', $html);
    }

    // Concurrency: two near-simultaneous submissions must not duplicate ------

    public function test_two_submissions_to_the_same_record_lock_the_row_they_read(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Row locks (FOR UPDATE) are only emitted on MySQL; SQLite serialises writes itself.');
        }

        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $editorA = User::factory()->withRole('editor')->create();
        $editorB = User::factory()->withRole('editor')->create();

        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = strtolower($q->sql);
        });

        $approval = app(ContentApproval::class);
        $approval->submit($about, $editorA, ['title' => 'From A']);
        $approval->submit($about, $editorB, ['title' => 'From B']);

        $this->assertGreaterThanOrEqual(2, count(array_filter($queries, fn ($sql) => str_contains($sql, 'for update'))));

        // The second submission replaced the first, not duplicated it.
        $this->assertSame(1, PendingRevision::count());
        $this->assertSame('From B', PendingRevision::sole()->changes['title']);
        $this->assertSame($editorB->id, PendingRevision::sole()->submitted_by);
    }
}
