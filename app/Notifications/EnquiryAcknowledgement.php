<?php

namespace App\Notifications;

use App\Models\ContactSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * FR-CONT-05: acknowledgement to the visitor. It deliberately repeats none of
 * the submitted text, so the form can't be abused to send attacker-written
 * content to someone else's address.
 */
class EnquiryAcknowledgement extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactSubmission $submission, public string $siteName) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We received your enquiry')
            ->greeting('Thank you for contacting '.$this->siteName.'.')
            ->line('We have received your enquiry (reference #'.$this->submission->id.') and will reply as soon as we can.')
            ->line('If you did not send this enquiry, you can ignore this email.');
    }
}
