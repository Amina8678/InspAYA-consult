<?php

namespace App\Policies;

use App\Models\ContactSubmissionNote;
use App\Models\User;

/**
 * Internal enquiry notes: anyone who can respond adds notes; a note is
 * edited only by its author; deleting follows enquiry deletion rights, or
 * the note's own author, whichever the user has.
 */
class ContactSubmissionNotePolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('enquiries.respond');
    }

    public function update(User $user, ContactSubmissionNote $note): bool
    {
        return $user->hasPermission('enquiries.respond') && $note->user_id === $user->id;
    }

    public function delete(User $user, ContactSubmissionNote $note): bool
    {
        return $user->hasPermission('enquiries.delete') || $note->user_id === $user->id;
    }
}
