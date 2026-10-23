<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\BlogPost;
use App\Models\ContactSubmission;
use App\Models\Page;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AdminResetPassword;
use App\Notifications\EnquiryAcknowledgement;
use App\Notifications\EnquiryReceived;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
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
     * @return array<string, int>
     */
    private function stats(): array
    {
        return collect($this->get(route('admin.dashboard'))->assertOk()->viewData('stats'))
            ->pluck('value', 'label')->all();
    }

    public function test_super_admin_sees_every_panel(): void
    {
        $this->as('super-admin');
        Page::factory()->count(2)->create();
        Service::factory()->count(3)->create();
        BlogPost::factory()->published()->count(2)->create();
        BlogPost::factory()->draft()->create();
        BlogPost::factory()->inReview()->create();
        ContactSubmission::factory()->count(2)->create();
        ContactSubmission::factory()->closed()->create();

        $stats = $this->stats();

        $this->assertSame(2, $stats['Pages']);
        $this->assertSame(3, $stats['Services']);
        $this->assertSame(0, $stats['Consultants']);
        $this->assertSame(2, $stats['Published posts']);
        $this->assertSame(1, $stats['Posts in review']);
        $this->assertSame(1, $stats['Draft posts']);
        $this->assertSame(2, $stats['New enquiries']);

        $this->get(route('admin.dashboard'))
            ->assertSee('Recent posts')
            ->assertSee('Recent enquiries')
            ->assertSee('Recent activity');
    }

    public function test_editor_sees_content_and_enquiries_but_not_the_audit_log(): void
    {
        $this->as('editor');

        $this->assertEqualsCanonicalizing(
            ['Pages', 'Services', 'Consultants', 'Published posts', 'Posts in review', 'Draft posts', 'New enquiries'],
            array_keys($this->stats()),
        );
        $this->get(route('admin.dashboard'))->assertDontSee('Recent activity')->assertViewHas('activity', null);
    }

    public function test_author_sees_only_counts_and_posts_of_their_own(): void
    {
        $author = $this->as('author');
        BlogPost::factory()->for($author, 'author')->draft()->count(2)->create();
        BlogPost::factory()->for($author, 'author')->published()->create(['title' => 'My published post']);
        BlogPost::factory()->published()->count(5)->create(['title' => 'Someone else']);
        ContactSubmission::factory()->create();

        $this->assertSame(
            ['Your published posts' => 1, 'Your posts in review' => 0, 'Your draft posts' => 2],
            $this->stats(),
        );

        $this->get(route('admin.dashboard'))
            ->assertSee('Your recent posts')
            ->assertSee('My published post')
            ->assertDontSee('Someone else')
            ->assertDontSee('Recent enquiries')
            ->assertDontSee('Recent activity');
    }

    public function test_alerts_are_shown_only_to_people_who_can_act_on_them(): void
    {
        $this->as('administrator');
        $this->get(route('admin.dashboard'))->assertSee('Privacy Policy page is not published');

        $this->as('author');
        $this->get(route('admin.dashboard'))->assertViewHas('alerts', []);
    }

    private function failedJob(string $notification, string $uuid): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid, 'connection' => 'database', 'queue' => 'default',
            'payload' => json_encode(['displayName' => $notification]),
            'exception' => 'TransportException', 'failed_at' => now(),
        ]);
    }

    public function test_failed_company_notification_emails_are_named_and_link_to_the_inbox(): void
    {
        $this->as('administrator');
        $this->failedJob(EnquiryReceived::class, '00000000-0000-0000-0000-000000000001');
        $this->failedJob(EnquiryReceived::class, '00000000-0000-0000-0000-000000000002');

        $response = $this->get(route('admin.dashboard'))->assertOk();

        $response->assertSee('2 contact-form notifications to the company could not be emailed');
        $response->assertSee('The enquiries are saved');
        $response->assertSee('href="'.route('admin.enquiries.index').'"', false);
    }

    public function test_other_failed_jobs_get_the_retry_instructions_and_visitor_emails_are_named(): void
    {
        $this->as('super-admin');
        $this->failedJob(EnquiryAcknowledgement::class, '00000000-0000-0000-0000-000000000001');
        $this->failedJob(AdminResetPassword::class, '00000000-0000-0000-0000-000000000002');

        $this->get(route('admin.dashboard'))
            ->assertSee('2 background jobs failed')
            ->assertSee('including 1 confirmation email to visitors')
            ->assertSee('php artisan queue:retry all');
    }

    public function test_a_company_notification_failure_is_not_double_counted_as_a_generic_failed_job(): void
    {
        $this->as('super-admin');
        $this->failedJob(EnquiryReceived::class, '00000000-0000-0000-0000-000000000001');

        $this->get(route('admin.dashboard'))
            ->assertSee('1 contact-form notification to the company')
            ->assertDontSee('background job failed')
            ->assertDontSee('background jobs failed');
    }

    public function test_a_job_waiting_too_long_says_the_worker_may_not_be_running(): void
    {
        $this->as('administrator');
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subHour()->timestamp, 'created_at' => now()->subHour()->timestamp]);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);

        $this->get(route('admin.dashboard'))->assertSee('Queued jobs (such as emails) have been waiting over 15 minutes (1 so far)')
            ->assertSee('queue worker may not be running');
    }

    public function test_a_recent_queue_backlog_is_not_an_alert(): void
    {
        $this->as('administrator');
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);

        $this->get(route('admin.dashboard'))->assertDontSee('waiting over');
    }

    public function test_queue_alerts_are_not_shown_to_people_who_cannot_fix_them(): void
    {
        $this->as('editor');
        $this->failedJob(AdminResetPassword::class, '00000000-0000-0000-0000-000000000001');

        $this->get(route('admin.dashboard'))->assertDontSee('background job');
    }

    public function test_user_supplied_text_is_escaped(): void
    {
        $this->as('super-admin');
        ContactSubmission::factory()->create(['subject' => '<script>alert(1)</script>', 'name' => '<b>Visitor</b>']);
        AuditLog::factory()->anonymous()->create(['new_values' => ['email' => '<img src=x onerror=alert(2)>']]);

        $this->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<b>Visitor</b>', false)
            ->assertDontSee('<img src=x onerror=alert(2)>', false);
    }

    public function test_query_count_does_not_grow_with_activity_or_posts(): void
    {
        $admin = $this->as('super-admin');
        AuditLog::factory()->count(2)->create();
        BlogPost::factory()->count(2)->create();
        // A fresh user instance per request, as in real requests (permissions
        // are remembered on the instance for one request only).
        $few = $this->countQueries(fn () => $this->actingAs($admin->fresh())->get(route('admin.dashboard'))->assertOk());

        AuditLog::factory()->count(12)->create();
        BlogPost::factory()->count(8)->create();
        $many = $this->countQueries(fn () => $this->actingAs($admin->fresh())->get(route('admin.dashboard'))->assertOk());

        $this->assertSame($few, $many);
    }

    private function countQueries(callable $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $this->app->forgetScopedInstances();
        $callback();
        DB::getEventDispatcher()->forget('Illuminate\Database\Events\QueryExecuted');

        return $count;
    }
}
