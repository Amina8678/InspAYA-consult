<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\BlogPost;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Scheduled publishing (FR-BLOG-02, stage 17). The public site already goes
 * live on its own the moment a post's published_at passes —
 * BlogPost::scopePublished() is a plain query filter, evaluated fresh on
 * every request, so nothing here is needed for visibility.
 *
 * What a query filter alone can't give: (1) an audit trail entry for the
 * moment content actually became public, distinct from the moment an admin
 * scheduled it (which may have been logged days earlier) — NFR-SEC-07 wants
 * publication actions audited, and "this went live" is one even when no
 * admin was at a keyboard for it; (2) a bound on how stale the sitemap can
 * be for a scheduled post specifically, tighter than its normal 5-minute
 * cache TTL (SeoController::CACHE_SECONDS).
 *
 * Idempotent: a post already carries a 'went_live' audit entry once handled,
 * so running this twice (or catching up after downtime) never double-fires.
 */
class AnnounceScheduledPosts extends Command
{
    protected $signature = 'posts:announce-scheduled';

    protected $description = 'Record an audit entry for scheduled posts whose publish date has just passed, and refresh the sitemap';

    public function handle(AuditLogger $audit): int
    {
        $due = BlogPost::query()
            ->where('status', PostStatus::Published)
            ->where('published_at', '<=', now())
            ->whereDoesntHave('auditTrail', fn ($q) => $q->where('action', 'went_live'))
            ->get();

        if ($due->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($due as $post) {
            // No actor: nobody was at a keyboard for this, the same reasoning
            // AuditLogger already uses for a null user_id (system events).
            $audit->record('went_live', null, $post, new: ['published_at' => $post->published_at->toIso8601String()]);
            $this->info("Announced \"{$post->title}\" (published_at {$post->published_at}).");
        }

        // Scheduled posts would otherwise wait out the normal cache TTL to
        // appear in the sitemap; this tightens that to effectively zero.
        Cache::forget('sitemap.xml');

        return self::SUCCESS;
    }
}
