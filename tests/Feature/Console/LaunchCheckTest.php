<?php

namespace Tests\Feature\Console;

use App\Models\CoreValue;
use App\Models\Page;
use App\Models\Service;
use App\Models\SiteSetting;
use Database\Seeders\SiteSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LaunchCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A launchable site: production, SMTP, a proxy, real settings, both
        // legal pages published, no failed jobs. Each test then breaks one thing.
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'app.debug' => false,
            'app.name' => 'InspAya Consult',
            'mail.default' => 'smtp',
            'mail.from.address' => 'no-reply@inspaya.test',
            'trustedproxy.proxies' => '10.0.0.5',
        ]);

        (new SiteSettingsSeeder)->run();
        $this->setting('email.enquiry_recipient', 'enquiries@inspaya.test');
        $this->setting('contact.email', 'hello@inspaya.test');
        $this->setting('contact.phone', '+234 800 000 0000');
        $this->setting('contact.address', '1 Real Street');
        $this->setting('seo.default_description', 'Consulting.');

        foreach (['privacy-policy', 'terms-of-service'] as $slug) {
            Page::factory()->published()->create(['slug' => $slug, 'title' => $slug, 'structured_content' => null, 'meta_description' => null]);
        }
    }

    private function setting(string $key, ?string $value): void
    {
        SiteSetting::where('key', $key)->update(['value' => $value]);
    }

    private function assertFails(string $expected): void
    {
        $this->artisan('launch:check')
            ->expectsOutputToContain($expected)
            ->assertExitCode(1);
    }

    public function test_a_launchable_site_passes(): void
    {
        $this->artisan('launch:check')->expectsOutputToContain('Launch check passed.')->assertSuccessful();
    }

    public function test_a_fresh_production_seed_is_not_launchable(): void
    {
        SiteSetting::query()->update(['value' => null]);
        Page::query()->delete();

        $this->artisan('launch:check')->assertExitCode(1);
    }

    public function test_it_fails_outside_production(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $this->assertFails('APP_ENV is "local"');
    }

    public function test_it_fails_with_debug_on(): void
    {
        config(['app.debug' => true]);

        $this->assertFails('APP_DEBUG is true');
    }

    public function test_it_fails_with_the_default_app_name(): void
    {
        config(['app.name' => 'Laravel']);

        $this->assertFails('APP_NAME is still the default');
    }

    public function test_it_fails_with_a_non_delivering_mailer(): void
    {
        foreach (['log', 'array', 'null'] as $mailer) {
            config(['mail.default' => $mailer]);

            $this->assertFails('MAIL_MAILER is "'.$mailer.'"');
        }
    }

    public function test_it_fails_with_a_placeholder_sender_address(): void
    {
        config(['mail.from.address' => 'hello@example.com']);

        $this->assertFails('MAIL_FROM_ADDRESS');
    }

    public function test_it_fails_with_no_trusted_proxies_unless_direct_tls_is_acknowledged(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->assertFails('TRUSTED_PROXIES is empty');
        $this->artisan('launch:check', ['--direct-tls' => true])->expectsOutputToContain('Launch check passed.')->assertSuccessful();
    }

    public function test_it_fails_with_a_placeholder_or_example_com_setting(): void
    {
        $this->setting('contact.address', '[PLACEHOLDER] Office address awaiting client confirmation');
        $this->assertFails('Site setting "contact.address"');

        $this->setting('contact.address', '1 Real Street');
        $this->setting('contact.email', 'hello@example.com');
        $this->assertFails('Site setting "contact.email"');

        $this->setting('contact.email', 'hello@inspaya.test');
        $this->setting('contact.phone', 'https://example.org/call');
        $this->assertFails('Site setting "contact.phone"');
    }

    public function test_a_real_address_containing_the_word_example_is_not_a_placeholder(): void
    {
        $this->setting('contact.address', '5 Counterexample Road');

        $this->artisan('launch:check')->assertSuccessful();
    }

    public function test_it_fails_with_no_enquiry_recipient(): void
    {
        $this->setting('email.enquiry_recipient', null);

        $this->assertFails('No enquiry recipient');
    }

    public function test_it_fails_when_a_legal_page_is_unpublished_or_missing(): void
    {
        Page::where('slug', 'privacy-policy')->update(['status' => 'draft']);
        $this->assertFails('Privacy Policy page is not published');

        Page::where('slug', 'privacy-policy')->update(['status' => 'published']);
        Page::where('slug', 'terms-of-service')->delete();
        $this->assertFails('Terms of Service page is not published');
    }

    public function test_it_fails_with_failed_jobs(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => '00000000-0000-0000-0000-000000000001',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'boom',
            'failed_at' => now(),
        ]);

        $this->assertFails('1 failed background job');
    }

    public function test_it_fails_when_placeholder_copy_has_been_activated_or_published(): void
    {
        $service = Service::factory()->active()->create(['title' => 'Legal', 'short_description' => '[PLACEHOLDER: awaiting copy]']);
        $this->assertFails('Active service "Legal"');
        $service->update(['short_description' => 'Real']);

        CoreValue::factory()->create(['title' => 'Integrity', 'description' => '[PLACEHOLDER] x', 'is_active' => true]);
        $this->assertFails('Active core value "Integrity"');
        CoreValue::query()->update(['description' => 'Real']);

        Page::where('slug', 'privacy-policy')->update(['structured_content' => ['body' => '[PLACEHOLDER] text']]);
        $this->assertFails('Published page "privacy-policy"');
    }

    public function test_inactive_placeholder_copy_is_fine(): void
    {
        Service::factory()->inactive()->create(['short_description' => '[PLACEHOLDER: awaiting copy]']);
        CoreValue::factory()->inactive()->create(['description' => '[PLACEHOLDER] x']);

        $this->artisan('launch:check')->assertSuccessful();
    }

    public function test_blank_contact_details_only_warn(): void
    {
        $this->setting('contact.phone', null);

        $this->artisan('launch:check')
            ->expectsOutputToContain('contact.phone')
            ->assertSuccessful();
    }

    public function test_it_changes_nothing(): void
    {
        $before = [SiteSetting::count(), Page::count(), DB::table('failed_jobs')->count()];

        $this->artisan('launch:check');

        $this->assertSame($before, [SiteSetting::count(), Page::count(), DB::table('failed_jobs')->count()]);
    }
}
