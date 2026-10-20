<?php

namespace Tests\Feature\Console;

use App\Models\AuditLog;
use App\Models\Page;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneLoginAuditNoiseTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_only_old_login_failed_and_login_throttled_rows(): void
    {
        $old = now()->subDays(91);
        $recent = now()->subDays(10);
        $page = Page::factory()->create();

        $oldFailed = AuditLog::factory()->anonymous('login_failed')->create(['created_at' => $old]);
        $oldThrottled = AuditLog::factory()->anonymous('login_throttled')->create(['created_at' => $old]);
        $recentFailed = AuditLog::factory()->anonymous('login_failed')->create(['created_at' => $recent]);
        $recentThrottled = AuditLog::factory()->anonymous('login_throttled')->create(['created_at' => $recent]);

        // Equally old, but not login noise: must survive.
        $keep = collect(['login', 'logout', 'password_reset', 'deleted', 'published', 'exported'])
            ->map(fn (string $action) => AuditLog::factory()->forEntity($page, $action)->create(['created_at' => $old]));

        $this->artisan('audit:prune-login-noise')->assertSuccessful();

        $this->assertModelMissing($oldFailed);
        $this->assertModelMissing($oldThrottled);
        $this->assertModelExists($recentFailed);
        $this->assertModelExists($recentThrottled);
        $keep->each(fn (AuditLog $log) => $this->assertModelExists($log));
    }

    public function test_days_option_overrides_the_default_and_bad_values_are_rejected(): void
    {
        $failed = AuditLog::factory()->anonymous('login_failed')->create(['created_at' => now()->subDays(10)]);

        $this->artisan('audit:prune-login-noise --days=0')->assertFailed();
        $this->artisan('audit:prune-login-noise --days=abc')->assertFailed();
        $this->artisan('audit:prune-login-noise --days=-5')->assertFailed();
        $this->assertModelExists($failed);

        $this->artisan('audit:prune-login-noise --days=7')->assertSuccessful();
        $this->assertModelMissing($failed);
    }

    public function test_default_retention_comes_from_config(): void
    {
        config(['inspaya.audit_login_retention_days' => 5]);
        $failed = AuditLog::factory()->anonymous('login_failed')->create(['created_at' => now()->subDays(6)]);

        $this->artisan('audit:prune-login-noise')->assertSuccessful();

        $this->assertModelMissing($failed);
    }

    public function test_eloquent_deletes_remain_blocked(): void
    {
        $log = AuditLog::factory()->anonymous('login_failed')->create();

        $this->expectException(\LogicException::class);
        $log->delete();
    }

    public function test_it_is_registered_in_the_scheduler(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command, 'audit:prune-login-noise'));

        $this->assertCount(1, $events);
    }
}
