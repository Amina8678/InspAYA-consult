<?php

namespace Tests\Feature\Console;

use App\Models\AuditLog;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Scheduled publishing (FR-BLOG-02, stage 17). Public visibility already
 * works via BlogPost::scopePublished() alone (a plain query filter); this
 * command only adds the audit trail entry for the actual go-live moment and
 * a tighter sitemap-freshness bound — see AnnounceScheduledPosts's docblock.
 */
class AnnounceScheduledPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_post_whose_publish_date_has_passed_is_announced_once(): void
    {
        $post = BlogPost::factory()->published()->create(['published_at' => now()->subMinute()]);

        $this->artisan('posts:announce-scheduled')->assertSuccessful();

        $log = AuditLog::where('action', 'went_live')->sole();
        $this->assertNull($log->user_id);
        $this->assertSame((new BlogPost)->getMorphClass(), $log->entity_type);
        $this->assertSame($post->id, $log->entity_id);

        // Idempotent: running again does not duplicate it.
        $this->artisan('posts:announce-scheduled')->assertSuccessful();
        $this->assertSame(1, AuditLog::where('action', 'went_live')->count());
    }

    public function test_a_post_still_scheduled_for_the_future_is_not_announced(): void
    {
        BlogPost::factory()->published()->create(['published_at' => now()->addDay()]);

        $this->artisan('posts:announce-scheduled')->assertSuccessful();

        $this->assertSame(0, AuditLog::where('action', 'went_live')->count());
    }

    public function test_draft_and_review_posts_are_never_announced_even_with_a_past_date(): void
    {
        BlogPost::factory()->draft()->create(['published_at' => now()->subMinute()]);
        BlogPost::factory()->inReview()->create(['published_at' => now()->subMinute()]);

        $this->artisan('posts:announce-scheduled')->assertSuccessful();

        $this->assertSame(0, AuditLog::where('action', 'went_live')->count());
    }

    public function test_the_sitemap_cache_is_cleared_only_when_something_was_announced(): void
    {
        Cache::put('sitemap.xml', '<urlset></urlset>', 300);
        BlogPost::factory()->published()->create(['published_at' => now()->addDay()]);

        $this->artisan('posts:announce-scheduled')->assertSuccessful();
        $this->assertTrue(Cache::has('sitemap.xml'), 'Nothing was due, so the cache should be left alone.');

        BlogPost::factory()->published()->create(['published_at' => now()->subMinute()]);
        $this->artisan('posts:announce-scheduled')->assertSuccessful();
        $this->assertFalse(Cache::has('sitemap.xml'));
    }
}
