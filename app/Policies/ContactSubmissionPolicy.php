<?php

namespace App\Policies;

use App\Models\ContactSubmission;
use App\Models\User;

/**
 * §7 "Manage enquiries" (Full / Full / View-Respond / No), plan §6 rows
 * 27–31. Visitors create submissions through the public form, never here.
 */
class ContactSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('enquiries.view');
    }

    public function view(User $user, ContactSubmission $submission): bool
    {
        return $user->hasPermission('enquiries.view');
    }

    /**
     * Change status and add internal notes.
     */
    public function respond(User $user, ContactSubmission $submission): bool
    {
        return $user->hasPermission('enquiries.respond');
    }

    public function assign(User $user, ContactSubmission $submission): bool
    {
        return $user->hasPermission('enquiries.assign');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('enquiries.export');
    }

    public function delete(User $user, ContactSubmission $submission): bool
    {
        return $user->hasPermission('enquiries.delete');
    }
}
