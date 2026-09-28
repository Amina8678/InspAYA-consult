<?php

namespace App\Notifications;

use App\Models\ContactSubmission;
use App\Support\MarkdownText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * FR-CONT-05: tells the company a new enquiry arrived. Queued, so the visitor
 * never waits on SMTP. Visitor text is Markdown-escaped.
 */
class EnquiryReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactSubmission $submission) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->submission;
        $e = fn (?string $value) => $value === null || $value === '' ? '(not given)' : MarkdownText::escape($value);

        return (new MailMessage)
            ->subject('New website enquiry #'.$s->id)
            ->replyTo($s->email, $s->name)
            ->line('A new enquiry was submitted through the website contact form.')
            ->line('**Name:** '.$e($s->name))
            ->line('**Email:** '.$e($s->email))
            ->line('**Phone:** '.$e($s->phone))
            ->line('**Organization:** '.$e($s->organization))
            ->line('**Subject:** '.$e($s->subject))
            ->line('**Message:**')
            ->line($e($s->message))
            ->line('Received '.$s->created_at?->toDayDateTimeString().'. It is stored in the CMS enquiry inbox as #'.$s->id.'.');
    }
}
