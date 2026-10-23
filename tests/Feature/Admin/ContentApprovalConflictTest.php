<?php

namespace Tests\Feature\Admin;

use App\Enums\PendingRevisionStatus;
use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\PendingRevision;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Approving a pending revision when the live record has changed since it was
 * submitted, or when its slug has been taken (stage 16b).
 */
class ContentApprovalConflictTest extends TestCase
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

    private function pendingFor(Page|BlogPost $record, array $changes, bool $withBaseline = true): PendingRevision
    {
        return PendingRevision::factory()->create([
            'revisable_type' => $record->getMorphClass(), 'revisable_id' => $record->id,
            'submitted_by' => User::factory()->withRole('editor')->create()->id,
            'changes' => $changes,
            'base_updated_at' => $withBaseline ? $record->updated_at : null,
        ]);
    }

    public function test_submitting_records_the_live_records_updated_at(): void
    {
        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $this->as('editor');

        $this->put(route('admin.pages.update', $about), [
            'title' => 'About us v2', 'slug' => '', 'meta_title' => '', 'meta_description' => '', 'canonical_url' => '', 'og_image_id' => '',
            'sections' => [['type' => 'text', 'heading' => 'How we work', 'body' => 'Updated body copy.']],
        ])->assertSessionHasNoErrors();

        $this->assertTrue(PendingRevision::sole()->base_updated_at->equalTo($about->fresh()->updated_at));
    }

    public function test_an_unchanged_record_approves_without_any_confirmation(): void
    {
        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $revision = $this->pendingFor($about, ['title' => 'About us, revised']);
        $this->as('administrator');

        $this->get(route('admin.content-approvals.show', $revision))->assertOk()
            ->assertDontSee('changed since the edit was submitted')->assertDontSee('Approve anyway');
        $this->post(route('admin.content-approvals.approve', $revision))->assertRedirect(route('admin.content-approvals.index'));

        $this->assertSame('About us, revised', $about->fresh()->title);
    }

    public function test_approval_is_refused_when_the_live_page_changed_since_submission(): void
    {
        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $revision = $this->pendingFor($about, ['title' => 'Editor title']);
        $this->travel(5)->minutes();
        $about->update(['title' => 'Admin title']);
        $this->as('administrator');

        $this->post(route('admin.content-approvals.approve', $revision))
            ->assertRedirect(route('admin.content-approvals.show', $revision))
            ->assertSessionHasErrors('approve');
        $this->assertStringContainsString('changed after the edit was submitted', session('errors')->first('approve'));

        $this->assertSame('Admin title', $about->fresh()->title, 'Nothing was overwritten.');
        $this->assertSame(PendingRevisionStatus::Pending, $revision->fresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'revision_approved')->count());
    }

    public function test_the_review_screen_warns_and_offers_approve_anyway_or_reject(): void
    {
        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $revision = $this->pendingFor($about, ['title' => 'Editor title']);
        $this->travel(5)->minutes();
        $about->update(['title' => 'Admin title']);
        $this->as('administrator');

        $this->get(route('admin.content-approvals.show', $revision))->assertOk()
            ->assertSee('changed since the edit was submitted')
            ->assertSeeInOrder(['Title', 'Admin title', 'Editor title'])
            ->assertSee('name="confirm_live_change"', false)
            ->assertSee('Approve anyway')
            ->assertSee('Reject with this reason');
    }

    public function test_approve_anyway_applies_the_change_and_the_audit_says_so(): void
    {
        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $revision = $this->pendingFor($about, ['title' => 'Editor title']);
        $this->travel(5)->minutes();
        $about->update(['title' => 'Admin title']);
        $this->as('administrator');

        $this->post(route('admin.content-approvals.approve', $revision), ['confirm_live_change' => '1'])
            ->assertRedirect(route('admin.content-approvals.index'));

        $this->assertSame('Editor title', $about->fresh()->title);
        $log = AuditLog::firstWhere('action', 'revision_approved');
        $this->assertSame('Admin title', $log->old_values['title']);
        $this->assertTrue($log->new_values['approved_after_live_change']);
    }

    public function test_a_stale_post_revision_is_refused_the_same_way(): void
    {
        $post = BlogPost::factory()->published()->create(['title' => 'Post']);
        $revision = $this->pendingFor($post, ['title' => 'Editor post title']);
        $this->travel(5)->minutes();
        $post->update(['excerpt' => 'Edited meanwhile']);
        $this->as('administrator');

        $this->post(route('admin.content-approvals.approve', $revision))->assertSessionHasErrors('approve');
        $this->assertSame('Post', $post->fresh()->title);
    }

    public function test_a_revision_from_before_baselines_existed_is_never_called_stale(): void
    {
        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $revision = $this->pendingFor($about, ['title' => 'Old-style revision'], withBaseline: false);
        $this->travel(5)->minutes();
        $about->update(['title' => 'Edited since']);
        $this->as('administrator');

        $this->get(route('admin.content-approvals.show', $revision))->assertDontSee('changed since the edit was submitted');
        $this->post(route('admin.content-approvals.approve', $revision))->assertSessionHasNoErrors();
        $this->assertSame('Old-style revision', $about->fresh()->title);
    }

    public function test_a_slug_taken_in_the_meantime_is_a_clear_error_not_a_500(): void
    {
        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $revision = $this->pendingFor($about, ['slug' => 'company', 'title' => 'Company']);
        Page::factory()->published()->create(['slug' => 'company']);
        $this->as('administrator');

        $this->get(route('admin.content-approvals.show', $revision))->assertOk()
            ->assertSee('now used by another page or post')
            ->assertDontSee('Approve and publish this change');

        // Not even the confirmation gets past a taken slug.
        $this->post(route('admin.content-approvals.approve', $revision), ['confirm_live_change' => '1'])
            ->assertRedirect(route('admin.content-approvals.show', $revision))
            ->assertSessionHasErrors('approve');
        $this->assertStringContainsString('The URL "company" is now used', session('errors')->first('approve'));

        $this->assertSame('about', $about->fresh()->slug);
        $this->assertSame(PendingRevisionStatus::Pending, $revision->fresh()->status);
    }

    public function test_a_taken_post_slug_is_refused_too_and_the_revision_can_still_be_rejected(): void
    {
        $post = BlogPost::factory()->published()->create(['slug' => 'first']);
        $revision = $this->pendingFor($post, ['slug' => 'second']);
        BlogPost::factory()->published()->create(['slug' => 'second']);
        $this->as('administrator');

        $this->post(route('admin.content-approvals.approve', $revision))->assertSessionHasErrors('approve');
        $this->assertSame('first', $post->fresh()->slug);

        $this->post(route('admin.content-approvals.reject', $revision), ['reason' => 'Pick another URL'])
            ->assertRedirect(route('admin.content-approvals.index'));
        $this->assertSame(PendingRevisionStatus::Rejected, $revision->fresh()->status);
    }

    public function test_an_unchanged_slug_is_not_a_conflict_with_itself(): void
    {
        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => 'About us']);
        $revision = $this->pendingFor($about, ['slug' => 'about', 'title' => 'About us v2']);
        $this->as('administrator');

        $this->post(route('admin.content-approvals.approve', $revision))->assertSessionHasNoErrors();
        $this->assertSame('About us v2', $about->fresh()->title);
    }
}
