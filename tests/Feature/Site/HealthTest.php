<?php

namespace Tests\Feature\Site;

use App\Http\Controllers\HealthController;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    private function breakDatabase(): void
    {
        DB::partialMock()->shouldReceive('select')->andThrow(new \PDOException('SQLSTATE[HY000] [2002] Connection refused'));
    }

    private function failedJob(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => 'a0000000-0000-0000-0000-000000000001', 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'secret internal detail', 'failed_at' => now(),
        ]);
    }

    public function test_up_is_public_and_reports_ok_with_nothing_else(): void
    {
        $this->failedJob();
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);

        $this->get('/up')
            ->assertOk()
            ->assertExactJson(['status' => 'ok'])
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex');
    }

    public function test_up_does_not_start_a_session_or_set_cookies(): void
    {
        $response = $this->get('/up')->assertOk();

        $this->assertSame([], $response->headers->getCookies());
        $this->assertSame(0, DB::table('sessions')->count());
    }

    public function test_up_returns_503_without_detail_when_the_database_is_down(): void
    {
        $this->breakDatabase();

        $this->get('/up')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
    }

    public function test_up_still_carries_the_security_headers(): void
    {
        $this->get('/up')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeaderMissing('Set-Cookie');
    }

    public function test_detail_is_for_guests_a_redirect_to_login(): void
    {
        $this->get('/admin/health')->assertRedirect(route('admin.login'));
    }

    public function test_detail_is_forbidden_to_staff_without_settings_access(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $editor = User::factory()->withRole('editor')->create();

        $this->actingAs($editor)->get('/admin/health')->assertForbidden();
    }

    public function test_detail_reports_queue_failed_jobs_and_scheduler_to_an_admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->withRole('administrator')->create();
        $this->failedJob();
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->subMinutes(10)->timestamp]);
        Cache::forever(HealthController::SCHEDULER_HEARTBEAT, now()->subSeconds(30)->timestamp);

        $response = $this->actingAs($admin)->get('/admin/health')->assertOk();

        $response->assertJson(['database' => 'ok', 'queue_pending' => 1, 'failed_jobs' => 1]);
        $this->assertEqualsWithDelta(600, $response->json('queue_oldest_pending_seconds'), 5);
        $this->assertEqualsWithDelta(30, $response->json('scheduler_last_run_seconds'), 5);
        $response->assertDontSee('secret internal detail');
    }

    public function test_detail_says_null_when_the_scheduler_has_never_run(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->withRole('administrator')->create();

        $this->actingAs($admin)->get('/admin/health')->assertJson(['scheduler_last_run_seconds' => null, 'queue_pending' => 0]);
    }

    public function test_the_scheduler_registers_the_heartbeat(): void
    {
        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertNotNull(Cache::get(HealthController::SCHEDULER_HEARTBEAT));
    }
}
