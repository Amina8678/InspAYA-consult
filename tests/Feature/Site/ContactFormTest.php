<?php

namespace Tests\Feature\Site;

use App\Http\Controllers\Site\ContactController;
use App\Models\ContactSubmission;
use App\Models\SiteSetting;
use App\Notifications\EnquiryAcknowledgement;
use App\Notifications\EnquiryReceived;
use Database\Seeders\SiteSettingsSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;

class ContactFormTest extends SiteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SiteSettingsSeeder::class);
    }

    /**
     * @return array<string, string>
     */
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ada Example',
            'email' => 'ada@example.com',
            'phone' => '+1 555 0123',
            'organization' => 'Example Ltd',
            'subject' => 'Governance review',
            'message' => "Hello,\nWe would like to discuss a review.",
            'consent' => '1',
            ContactController::HONEYPOT => '',
        ], $overrides);
    }

    private function submit(array $data, string $ip = '192.0.2.20')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->from(route('contact'))
            ->post(route('contact.store'), $data);
    }

    public function test_valid_submission_is_stored_and_both_emails_are_queued(): void
    {
        Notification::fake();

        $this->submit($this->valid())
            ->assertRedirect(route('contact'))
            ->assertSessionHas('status', ContactController::SUCCESS_MESSAGE)
            ->assertSessionHasNoErrors();

        $submission = ContactSubmission::sole();
        $this->assertSame('Ada Example', $submission->name);
        $this->assertSame('Example Ltd', $submission->organization);
        $this->assertSame("Hello,\nWe would like to discuss a review.", $submission->message);
        $this->assertSame('new', $submission->status->value);
        $this->assertNotNull($submission->consent_at);
        $this->assertSame('192.0.2.20', $submission->ip_address);

        Notification::assertSentOnDemand(EnquiryReceived::class,
            fn ($n, $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'enquiries@example.com'
                && $n->submission->is($submission)
                && $n instanceof ShouldQueue);
        Notification::assertSentOnDemand(EnquiryAcknowledgement::class,
            fn ($n, $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'ada@example.com'
                && $n instanceof ShouldQueue);
    }

    public function test_emails_go_through_the_queue(): void
    {
        Queue::fake();

        $this->submit($this->valid())->assertSessionHasNoErrors();

        Queue::assertPushed(SendQueuedNotifications::class, 2);
    }

    public function test_optional_fields_may_be_empty(): void
    {
        Notification::fake();

        $this->submit($this->valid(['phone' => '', 'organization' => '']))->assertSessionHasNoErrors();

        $this->assertNull(ContactSubmission::sole()->phone);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidSubmissions(): array
    {
        return [
            'name missing' => [['name' => ''], 'name'],
            'name too long' => [['name' => str_repeat('a', 256)], 'name'],
            'email missing' => [['email' => ''], 'email'],
            'email invalid' => [['email' => 'not-an-email'], 'email'],
            'phone with letters' => [['phone' => 'call me'], 'phone'],
            'phone too long' => [['phone' => str_repeat('1', 51)], 'phone'],
            'organization too long' => [['organization' => str_repeat('a', 256)], 'organization'],
            'subject missing' => [['subject' => ''], 'subject'],
            'message missing' => [['message' => ''], 'message'],
            'message too long' => [['message' => str_repeat('a', 5001)], 'message'],
            'consent missing' => [['consent' => ''], 'consent'],
            'consent refused' => [['consent' => '0'], 'consent'],
        ];
    }

    #[DataProvider('invalidSubmissions')]
    public function test_invalid_submission_is_rejected_and_not_stored(array $overrides, string $field): void
    {
        Notification::fake();

        $this->submit($this->valid($overrides))
            ->assertRedirect(route('contact'))
            ->assertSessionHasErrors($field)
            ->assertSessionMissing('status');

        $this->assertDatabaseCount('contact_submissions', 0);
        Notification::assertNothingSent();
    }

    public function test_consent_checkbox_is_required(): void
    {
        $data = $this->valid();
        unset($data['consent']);

        $this->submit($data)->assertSessionHasErrors([
            'consent' => 'Please confirm you have read the privacy notice before sending.',
        ]);
        $this->assertDatabaseCount('contact_submissions', 0);
    }

    public function test_filled_honeypot_is_rejected(): void
    {
        Notification::fake();

        $this->submit($this->valid([ContactController::HONEYPOT => 'https://spam.example']))
            ->assertSessionHasErrors(ContactController::HONEYPOT)
            ->assertSessionMissing('status');

        $this->assertDatabaseCount('contact_submissions', 0);
        Notification::assertNothingSent();
    }

    public function test_submissions_are_rate_limited_per_ip(): void
    {
        Notification::fake();

        for ($i = 0; $i < 3; $i++) {
            $this->submit($this->valid())->assertRedirect();
        }

        $this->submit($this->valid())->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('contact_submissions', 3);

        // Another visitor is unaffected.
        $this->submit($this->valid(), ip: '192.0.2.21')->assertSessionHasNoErrors();
    }

    public function test_hourly_cap_applies_after_the_minute_limit_resets(): void
    {
        Notification::fake();

        for ($i = 0; $i < 10; $i++) {
            $this->submit($this->valid())->assertRedirect();
            $this->travel(61)->seconds();
        }

        $this->submit($this->valid())->assertStatus(429);
    }

    public function test_company_email_escapes_visitor_markdown_and_ack_repeats_no_visitor_text(): void
    {
        Notification::fake();

        $this->submit($this->valid([
            'subject' => '[Click here](https://evil.example)',
            'message' => '![img](https://evil.example/x.png)',
        ]));

        Notification::assertSentOnDemand(EnquiryReceived::class, function (EnquiryReceived $n) {
            $lines = implode("\n", $n->toMail(new AnonymousNotifiable)->introLines);

            $this->assertStringContainsString('\[Click here\]\(https://evil\.example\)', $lines);
            $this->assertStringNotContainsString('[Click here](', $lines);

            return true;
        });

        Notification::assertSentOnDemand(EnquiryAcknowledgement::class, function (EnquiryAcknowledgement $n) {
            $mail = $n->toMail(new AnonymousNotifiable);
            $text = implode("\n", array_merge([$mail->subject, $mail->greeting], $mail->introLines, $mail->outroLines));

            $this->assertStringNotContainsString('evil.example', $text);
            $this->assertStringNotContainsString('Ada', $text);

            return true;
        });
    }

    public function test_company_email_falls_back_to_the_mail_from_address(): void
    {
        Notification::fake();
        SiteSetting::where('key', 'email.enquiry_recipient')->delete();
        config(['mail.from.address' => 'fallback@example.com']);

        $this->submit($this->valid());

        Notification::assertSentOnDemand(EnquiryReceived::class,
            fn ($n, $c, AnonymousNotifiable $to) => $to->routes['mail'] === 'fallback@example.com');
    }
}
