<?php

use App\Console\Commands\AnnounceScheduledPosts;
use App\Console\Commands\PruneLoginAuditNoise;
use App\Http\Controllers\HealthController;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled publishing (FR-BLOG-02, stage 17). Requires the Laravel
// scheduler to actually run in production — see docs/admin-auth.md
// "Scheduled publishing".
Schedule::command(AnnounceScheduledPosts::class)->everyMinute();

// Keeps anonymous login noise from growing audit_logs forever. Touches only
// login_failed / login_throttled — see docs/admin-auth.md "Audit log pruning".
Schedule::command(PruneLoginAuditNoise::class)->dailyAt('03:30');

// Heartbeat for /admin/health: proves the scheduler cron is actually running.
Schedule::call(fn () => Cache::forever(HealthController::SCHEDULER_HEARTBEAT, now()->timestamp))
    ->name('scheduler-heartbeat')
    ->everyMinute();
