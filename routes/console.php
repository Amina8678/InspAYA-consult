<?php

use App\Console\Commands\AnnounceScheduledPosts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled publishing (FR-BLOG-02, stage 17). Requires the Laravel
// scheduler to actually run in production — see docs/admin-auth.md
// "Scheduled publishing".
Schedule::command(AnnounceScheduledPosts::class)->everyMinute();
