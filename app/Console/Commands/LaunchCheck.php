<?php

namespace App\Console\Commands;

use App\Models\CoreValue;
use App\Models\Page;
use App\Models\Service;
use App\Models\SiteSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The last step before pointing a domain at the app: fails (exit 1, with a
 * list) while anything that must not be public or must work at launch is
 * still in its development state. Read-only; it changes nothing.
 *
 * Failures are things that would be wrong on a live site. Warnings are
 * things the client may legitimately launch without, and never affect the
 * exit code.
 */
class LaunchCheck extends Command
{
    protected $signature = 'launch:check
        {--direct-tls : This app terminates TLS itself with no reverse proxy, so an empty TRUSTED_PROXIES is correct}';

    protected $description = 'Fail if the app is not in a launchable state (placeholders, debug mode, mail, proxies, legal pages, failed jobs)';

    private const PLACEHOLDER = 'PLACEHOLDER';

    /** Mailers that never deliver anything. */
    private const NON_DELIVERING_MAILERS = ['log', 'array', 'null'];

    /** @var list<string> */
    private array $failures = [];

    /** @var list<string> */
    private array $warnings = [];

    public function handle(): int
    {
        $this->failures = $this->warnings = [];

        $this->checkEnvironment();
        $this->checkMail();
        $this->checkProxies();

        if (! Schema::hasTable('site_settings')) {
            $this->failures[] = 'The database has not been migrated (no site_settings table).';
        } else {
            $this->checkSettings();
            $this->checkLegalPages();
            $this->checkPublicContent();
            $this->checkFailedJobs();
        }

        foreach ($this->warnings as $warning) {
            $this->components->warn($warning);
        }

        if ($this->failures === []) {
            $this->components->info('Launch check passed.');

            return self::SUCCESS;
        }

        $this->components->error('Not ready to launch. Fix these '.count($this->failures).' problem(s):');
        foreach ($this->failures as $failure) {
            $this->line('  - '.$failure);
        }

        return self::FAILURE;
    }

    private function checkEnvironment(): void
    {
        if (! app()->environment('production')) {
            $this->failures[] = 'APP_ENV is "'.app()->environment().'", not "production".';
        }

        if (config('app.debug')) {
            $this->failures[] = 'APP_DEBUG is true. It leaks stack traces and configuration; set it to false.';
        }

        $name = (string) config('app.name');
        if ($name === '' || $name === 'Laravel') {
            $this->failures[] = 'APP_NAME is still the default ("Laravel"). Set it to the site name.';
        }
    }

    private function checkMail(): void
    {
        $mailer = (string) config('mail.default');
        if (in_array($mailer, self::NON_DELIVERING_MAILERS, true)) {
            $this->failures[] = 'MAIL_MAILER is "'.$mailer.'", which never delivers email. Configure SMTP.';
        }

        $from = (string) config('mail.from.address');
        if ($from === '' || $this->isPlaceholder($from)) {
            $this->failures[] = 'MAIL_FROM_ADDRESS is "'.$from.'". Set the real sender address.';
        }
    }

    private function checkProxies(): void
    {
        if (blank(config('trustedproxy.proxies')) && ! $this->option('direct-tls')) {
            $this->failures[] = 'TRUSTED_PROXIES is empty. Behind a TLS-terminating proxy this causes a redirect loop; '
                .'set it (docs/admin-auth.md "Trusted proxies"), or pass --direct-tls if the app serves HTTPS itself.';
        }
    }

    private function checkSettings(): void
    {
        $settings = SiteSetting::query()->pluck('value', 'key');

        foreach ($settings as $key => $value) {
            if (filled($value) && $this->isPlaceholder((string) $value)) {
                $this->failures[] = 'Site setting "'.$key.'" still holds a placeholder or example.com value. Replace it in Admin > Settings.';
            }
        }

        if (blank($settings->get('email.enquiry_recipient'))) {
            $this->failures[] = 'No enquiry recipient: set "Send new enquiries to" in Admin > Settings.';
        }

        foreach (['contact.email' => 'public email address', 'contact.phone' => 'public phone number', 'contact.address' => 'office address', 'seo.default_description' => 'default meta description'] as $key => $label) {
            if (blank($settings->get($key))) {
                $this->warnings[] = 'The '.$label.' ('.$key.') is blank, so the site shows none.';
            }
        }
    }

    private function checkLegalPages(): void
    {
        $published = Page::query()->published()->whereIn('slug', ['privacy-policy', 'terms-of-service'])->pluck('slug');

        foreach (['privacy-policy' => 'Privacy Policy', 'terms-of-service' => 'Terms of Service'] as $slug => $title) {
            if (! $published->contains($slug)) {
                $this->failures[] = 'The '.$title.' page is not published.';
            }
        }
    }

    /**
     * Placeholder copy must not be live: an admin who activates a seeded
     * service or core value, or publishes a page, without replacing its text.
     */
    private function checkPublicContent(): void
    {
        $like = '%'.self::PLACEHOLDER.'%';

        $services = Service::query()->where('is_active', true)->where(fn ($q) => $q
            ->where('title', 'like', $like)->orWhere('short_description', 'like', $like)
            ->orWhere('description', 'like', $like)->orWhere('meta_description', 'like', $like)
            ->orWhere('capabilities', 'like', $like)->orWhere('outcomes', 'like', $like))
            ->pluck('title');
        foreach ($services as $title) {
            $this->failures[] = 'Active service "'.$title.'" still contains placeholder text.';
        }

        $values = CoreValue::query()->where('is_active', true)
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like))
            ->pluck('title');
        foreach ($values as $title) {
            $this->failures[] = 'Active core value "'.$title.'" still contains placeholder text.';
        }

        $pages = Page::query()->published()->where(fn ($q) => $q
            ->where('title', 'like', $like)->orWhere('structured_content', 'like', $like)
            ->orWhere('meta_description', 'like', $like))
            ->pluck('title');
        foreach ($pages as $title) {
            $this->failures[] = 'Published page "'.$title.'" still contains placeholder text.';
        }

        if (! Service::query()->where('is_active', true)->exists()) {
            $this->warnings[] = 'No service is active, so the Services page is empty.';
        }

        if (! Page::query()->published()->where('slug', 'home')->exists()) {
            $this->warnings[] = 'The Home page is not published, so the home page shows only default content.';
        }
    }

    private function checkFailedJobs(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            return;
        }

        $failed = DB::table('failed_jobs')->count();
        if ($failed > 0) {
            $this->failures[] = "{$failed} failed background ".str('job')->plural($failed)
                .' (php artisan queue:failed). Retry or flush them: contact-form emails may not have been sent.';
        }
    }

    private function isPlaceholder(string $value): bool
    {
        return stripos($value, self::PLACEHOLDER) !== false
            || preg_match('/(?<![A-Za-z0-9-])example\.(com|org|net)\b/i', $value) === 1;
    }
}
