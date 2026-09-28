<?php

namespace Tests\Feature\Admin;

use App\Enums\PostStatus;
use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BlogPostAdminTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Board effectiveness in 2026',
            'slug' => '',
            'excerpt' => 'A short summary.',
            'content' => "First paragraph.\n\nSecond paragraph.",
            'category_id' => '',
            'meta_title' => '',
            'meta_description' => '',
            'canonical_url' => '',
            'featured_image_id' => '',
            'published_at' => '',
        ];
    }

    private function publicPage(string $route, mixed $param = null)
    {
        $this->app->forgetScopedInstances();

        return $this->get($param === null ? route($route) : route($route, $param));
    }

    // Round trip -------------------------------------------------------------

    public function test_an_author_writes_submits_and_an_administrator_publishes_it_onto_the_public_site(): void
    {
        $author = $this->as('author');
        $category = Category::factory()->create(['name' => 'Governance']);
        $tag = Tag::factory()->create(['name' => 'Strategy']);

        $this->post(route('admin.posts.store'), $this->payload([
            'category_id' => (string) $category->id,
            'tags' => [(string) $tag->id],
        ]))->assertSessionHasNoErrors();

        $post = BlogPost::sole();
        $this->assertSame($author->id, $post->author_id);
        $this->assertSame(PostStatus::Draft, $post->status);
        $this->assertSame([$tag->id], $post->tags()->pluck('tags.id')->all());

        // Submit for review: the only transition an Author may make.
        $this->put(route('admin.posts.update', $post), $this->payload([
            'category_id' => (string) $category->id, 'status' => 'review',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(PostStatus::Review, $post->fresh()->status);

        // Out of the author's hands now.
        $this->get(route('admin.posts.edit', $post))->assertForbidden();
        $this->put(route('admin.posts.update', $post), $this->payload())->assertForbidden();

        $this->actingAs(User::factory()->withRole('administrator')->create());
        $this->put(route('admin.posts.update', $post), $this->payload([
            'category_id' => (string) $category->id, 'status' => 'published',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(PostStatus::Published, $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);

        $index = $this->publicPage('insights.index')->assertOk();
        $index->assertSee('Board effectiveness in 2026');

        $show = $this->publicPage('insights.show', $post->fresh()->slug)->assertOk();
        $show->assertSee('Board effectiveness in 2026')
            ->assertSee('First paragraph.', false)
            ->assertSee('Governance')
            ->assertSee('Strategy');
    }

    public function test_an_author_cannot_reach_another_authors_posts(): void
    {
        $this->as('author');
        $other = BlogPost::factory()->create();

        $this->get(route('admin.posts.edit', $other))->assertForbidden();
        $this->put(route('admin.posts.update', $other), $this->payload())->assertForbidden();
        $this->get(route('admin.posts.delete', $other))->assertForbidden();
        $this->delete(route('admin.posts.destroy', $other), ['confirm' => '1'])->assertForbidden();
    }

    public function test_an_author_cannot_delete_even_their_own_post(): void
    {
        $author = $this->as('author');
        $own = BlogPost::factory()->for($author, 'author')->create();

        $this->get(route('admin.posts.delete', $own))->assertForbidden();
        $this->delete(route('admin.posts.destroy', $own), ['confirm' => '1'])->assertForbidden();
        $this->assertModelExists($own);
    }

    // Editor workflow ----------------------------------------------------------

    public function test_an_editor_edits_any_post_and_moves_it_between_draft_and_review_but_never_publishes(): void
    {
        $this->as('editor');
        $draft = BlogPost::factory()->draft()->create(['title' => 'Draft title']);
        $review = BlogPost::factory()->inReview()->create();
        $published = BlogPost::factory()->published()->create(['title' => 'Live already']);

        $this->put(route('admin.posts.update', $draft), $this->payload(['title' => 'Edited by editor', 'status' => 'review']))
            ->assertSessionHasNoErrors();
        $this->assertSame(PostStatus::Review, $draft->fresh()->status);
        $this->assertSame('Edited by editor', $draft->fresh()->title);

        // Sends it back for more work.
        $this->put(route('admin.posts.update', $review), $this->payload(['status' => 'draft']))->assertSessionHasNoErrors();
        $this->assertSame(PostStatus::Draft, $review->fresh()->status);

        // Editors may still edit the content of an already-published post…
        $this->put(route('admin.posts.update', $published), $this->payload(['title' => 'Edited while live']))->assertSessionHasNoErrors();
        $this->assertSame('Edited while live', $published->fresh()->title);
        // …but a crafted attempt to change its status is silently ignored, not applied.
        $this->put(route('admin.posts.update', $published), $this->payload(['title' => 'Edited while live', 'status' => 'draft']))
            ->assertSessionHasNoErrors();
        $this->assertSame(PostStatus::Published, $published->fresh()->status);

        $this->get(route('admin.posts.create'))->assertOk();
        $this->assertSame(0, AuditLog::whereIn('action', ['published', 'unpublished'])->count());
    }

    public function test_a_crafted_publish_request_from_an_editor_is_refused(): void
    {
        $this->as('editor');
        $post = BlogPost::factory()->inReview()->create();

        $this->put(route('admin.posts.update', $post), $this->payload(['status' => 'published']))->assertSessionHasNoErrors();

        $this->assertSame(PostStatus::Review, $post->fresh()->status);
    }

    public function test_a_crafted_publish_request_from_an_author_on_their_own_draft_is_refused(): void
    {
        $author = $this->as('author');
        $post = BlogPost::factory()->for($author, 'author')->draft()->create();

        $this->put(route('admin.posts.update', $post), $this->payload(['status' => 'published']))->assertSessionHasNoErrors();

        $this->assertSame(PostStatus::Draft, $post->fresh()->status);
        $this->assertNull($post->fresh()->published_at);
    }

    // Administrator: publish, unpublish, schedule -------------------------------

    public function test_publish_and_unpublish_are_audited(): void
    {
        $admin = $this->as('administrator');
        $post = BlogPost::factory()->inReview()->create(['slug' => 'board-effectiveness']);

        $this->put(route('admin.posts.update', $post), $this->payload(['status' => 'published']));
        $this->assertSame(PostStatus::Published, $post->fresh()->status);
        $this->publicPage('insights.show', 'board-effectiveness')->assertOk();

        $this->put(route('admin.posts.update', $post), $this->payload(['status' => 'draft']))
            ->assertSessionHas('status', 'Unpublished "Board effectiveness in 2026". It is now a draft.');
        $this->publicPage('insights.show', 'board-effectiveness')->assertNotFound();

        $this->assertSame(['published', 'unpublished'], AuditLog::whereIn('action', ['published', 'unpublished'])->orderBy('id')->pluck('action')->all());
        $this->assertSame($admin->id, AuditLog::firstWhere('action', 'unpublished')->user_id);
    }

    public function test_a_future_publish_date_schedules_the_post_and_keeps_it_off_the_public_site(): void
    {
        $this->as('administrator');
        $post = BlogPost::factory()->draft()->create(['slug' => 'future-post']);
        $future = now()->addDays(5)->format('Y-m-d\TH:i');

        $this->put(route('admin.posts.update', $post), $this->payload(['status' => 'published', 'published_at' => $future]))
            ->assertSessionHasNoErrors();

        $this->assertSame(PostStatus::Published, $post->fresh()->status);
        $this->assertTrue($post->fresh()->published_at->isFuture());
        $this->publicPage('insights.show', 'future-post')->assertNotFound();
        $this->publicPage('insights.index')->assertDontSee($post->title);

        // The admin editor still shows it.
        $this->get(route('admin.posts.edit', $post))->assertOk()->assertSee($post->fresh()->title);
    }

    public function test_changing_the_slug_of_a_published_post_needs_confirmation(): void
    {
        $this->as('administrator');
        $post = BlogPost::factory()->published()->create(['slug' => 'old-slug']);
        $data = $this->payload(['slug' => 'new-slug']);

        $this->put(route('admin.posts.update', $post), $data)->assertSessionHasErrors('confirm_slug_change');
        $this->assertSame('old-slug', $post->fresh()->slug);

        $this->put(route('admin.posts.update', $post), $data + ['confirm_slug_change' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('new-slug', $post->fresh()->slug);
    }

    // Validation -----------------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidFields(): array
    {
        return [
            'html in title' => [['title' => '<b>Bold</b>'], 'title'],
            'script in content' => [['content' => '<script>alert(1)</script>'], 'content'],
            'bad slug' => [['slug' => 'Not A Slug'], 'slug'],
            'javascript canonical url' => [['canonical_url' => 'javascript:alert(1)'], 'canonical_url'],
            'unknown category' => [['category_id' => '999999'], 'category_id'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_fields_are_rejected_and_nothing_is_saved(array $overrides, string $field): void
    {
        $this->as('administrator');

        $this->post(route('admin.posts.store'), $this->payload($overrides))->assertSessionHasErrors($field);

        $this->assertSame(0, BlogPost::count());
    }

    // Listing ----------------------------------------------------------------

    public function test_authors_see_only_their_own_posts_in_the_list(): void
    {
        $author = $this->as('author');
        BlogPost::factory()->for($author, 'author')->create(['title' => 'Mine']);
        BlogPost::factory()->create(['title' => 'Someone else\'s']);

        $this->get(route('admin.posts.index'))->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Someone else\'s');
    }

    public function test_editors_see_every_post_and_can_search(): void
    {
        $this->as('editor');
        BlogPost::factory()->create(['title' => '<script>alert(1)</script> Governance piece']);
        BlogPost::factory()->create(['title' => 'Unrelated']);

        $this->get(route('admin.posts.index', ['q' => 'governance']))->assertOk()
            ->assertViewHas('posts', fn ($p) => $p->total() === 1)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
