<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Password reset email, sent on the queue so the request never waits on SMTP
 * (and response time doesn't differ between known and unknown emails). The
 * link points at the admin reset page (see AppServiceProvider).
 */
class AdminResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
