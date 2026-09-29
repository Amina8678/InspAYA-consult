<?php

namespace Tests\Feature\Site;

use App\Enums\PostStatus;
use App\Http\Controllers\Site\InsightController;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\DataProvider;

class InsightRoutesTest extends SiteTestCase
{
    public function test_index_lists_only_published_posts_newest_first(): void
    {
        $old = BlogPost::factory()->published()->create(['published_at' => now()->subDays(10)]);
        $new = BlogPost::factory()->published()->create(['published_at' => now()->subDay()]);
        BlogPost::factory()->draft()->create();
        BlogPost::factory()->inReview()->create();
        BlogPost::factory()->published()->create(['published_at' => now()->addDay()]);
        BlogPost::factory()->create(['status' => PostStatus::Published, 'published_at' => null]);

        $response = $this->get(route('insights.index'));

        $response->assertOk()
            ->assertViewIs('public.insights.index')
            ->assertViewHas('posts', function (LengthAwarePaginator $posts) use ($old, $new) {
                $this->assertSame([$new->slug, $old->slug], array_column($posts->items(), 'slug'));

                return true;
            });
        $this->assertSeo($response);
    }

    public function test_index_is_paginated(): void
    {
        BlogPost::factory()->published()->count(InsightController::PER_PAGE + 2)->create();

        $this->get(route('insights.index'))->assertOk()
            ->assertViewHas('posts', fn (LengthAwarePaginator $posts) => $posts->count() === InsightController::PER_PAGE
                && $posts->total() === InsightController::PER_PAGE + 2
                && $posts->lastPage() === 2);

        $this->get(route('insights.index', ['page' => 2]))->assertOk()
            ->assertViewHas('posts', fn (LengthAwarePaginator $posts) => $posts->count() === 2);
    }

    public function test_post_summaries_expose_the_author_name_only(): void
    {
        BlogPost::factory()->published()->categorised()->withFeaturedImage()->create();

        $this->get(route('insights.index'))->assertOk()
            ->assertViewHas('posts', function (LengthAwarePaginator $posts) {
                $post = $posts->items()[0];

                $this->assertSame(['name'], array_keys($post['author']));
                $this->assertNotNull($post['category']);
                $this->assertNotNull($post['featured_image']['url']);
                $this->assertNotNull($post['published_on']);

                return true;
            });
    }

    public function test_show_renders_a_published_post_with_related_articles(): void
    {
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();
        $post = BlogPost::factory()->published()->withFeaturedImage()->create(['category_id' => $category->id]);
        $post->tags()->attach($tag);

        $sameCategory = BlogPost::factory()->published()->create(['category_id' => $category->id]);
        $sharedTag = BlogPost::factory()->published()->create();
        $sharedTag->tags()->attach($tag);
        BlogPost::factory()->published()->categorised()->create(['title' => 'Unrelated post']);
        BlogPost::factory()->draft()->create(['category_id' => $category->id]);

        $response = $this->get(route('insights.show', $post->slug));

        $response->assertOk()
            ->assertViewIs('public.insights.show')
            ->assertViewHas('post', function (array $data) use ($post, $sameCategory, $sharedTag) {
                $this->assertSame($post->content, $data['content']);
                $this->assertEqualsCanonicalizing(
                    [$sameCategory->slug, $sharedTag->slug],
                    array_column($data['related'], 'slug'),
                );
                $this->assertSame($data['featured_image'], $data['seo']['og_image']);

                return true;
            });
        $this->assertSeo($response);
    }

    public function test_post_canonical_url_is_used_when_set(): void
    {
        $post = BlogPost::factory()->published()->create(['canonical_url' => 'https://example.com/original']);

        $this->get(route('insights.show', $post->slug))
            ->assertViewHas('seo', fn (array $seo) => $seo['canonical_url'] === 'https://example.com/original');
    }

    /**
     * @return array<string, array{callable}>
     */
    public static function hiddenPosts(): array
    {
        return [
            'draft' => [fn () => BlogPost::factory()->draft()->create()],
            'in review' => [fn () => BlogPost::factory()->inReview()->create()],
            'future publish date' => [fn () => BlogPost::factory()->published()->create(['published_at' => now()->addHour()])],
            'published without a date' => [fn () => BlogPost::factory()->create(['status' => PostStatus::Published, 'published_at' => null])],
        ];
    }

    #[DataProvider('hiddenPosts')]
    public function test_unpublished_post_404s(callable $makePost): void
    {
        $post = $makePost();

        $this->get(route('insights.show', $post->slug))->assertNotFound();
    }

    public function test_unknown_slug_404s(): void
    {
        $this->get(route('insights.show', 'no-such-post'))->assertNotFound();
    }
}
