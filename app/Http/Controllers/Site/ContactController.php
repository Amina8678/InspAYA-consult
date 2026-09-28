<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use App\Notifications\EnquiryAcknowledgement;
use App\Notifications\EnquiryReceived;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Contact form submission (FR-CONT-01 to 05).
 *
 * Anti-bot (FR-CONT-03): the SRS names no CAPTCHA provider, so a honeypot
 * field plus per-IP rate limiting (the `contact` limiter) are used, with no
 * third-party service.
 */
class ContactController extends Controller
{
    public const SUCCESS_MESSAGE = 'Thank you. Your message has been received and we will be in touch soon.';

    /** Hidden field that people never fill in; bots usually do. */
    public const HONEYPOT = 'website';

    public function __construct(private SiteSettings $settings) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\-.\s]*$/'],
            'organization' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'consent' => ['accepted'],
            self::HONEYPOT => ['prohibited'],
        ], [
            'phone.regex' => 'Enter a phone number using digits, spaces and + ( ) - only.',
            'consent.accepted' => 'Please confirm you have read the privacy notice before sending.',
            self::HONEYPOT.'.prohibited' => 'Your message could not be sent. Please try again.',
        ]);

        // FR-CONT-04: stored before anything else; consent time and IP (D7)
        // are set by the server, never taken from the request body.
        $submission = ContactSubmission::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'organization' => $data['organization'] ?? null,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'consent_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        $this->notify($submission);

        return redirect()->route('contact')->with('status', self::SUCCESS_MESSAGE);
    }

    /**
     * Queue both emails (FR-CONT-05). The enquiry is already saved, so a
     * queue failure is reported to the logs rather than shown to the visitor.
     */
    private function notify(ContactSubmission $submission): void
    {
        try {
            $recipient = $this->settings->get('email.enquiry_recipient') ?: config('mail.from.address');

            if ($recipient) {
                Notification::route('mail', $recipient)->notify(new EnquiryReceived($submission));
            } else {
                report(new \RuntimeException('No enquiry recipient configured (site setting email.enquiry_recipient).'));
            }

            Notification::route('mail', $submission->email)->notify(new EnquiryAcknowledgement(
                $submission,
                (string) $this->settings->get('branding.site_name', config('app.name')),
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
