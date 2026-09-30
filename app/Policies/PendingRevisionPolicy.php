<?php

namespace App\Policies;

use App\Models\PendingRevision;
use App\Models\User;

/**
 * Content approval queue (stage 16). Reviewing a pending revision is making
 * edited content go live, which is exactly what content.publish already
 * gates — the same permission that governs publishing directly.
 */
class PendingRevisionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('content.publish');
    }

    public function view(User $user, PendingRevision $revision): bool
    {
        return $user->hasPermission('content.publish');
    }

    public function approve(User $user, PendingRevision $revision): bool
    {
        return $user->hasPermission('content.publish');
    }

    public function reject(User $user, PendingRevision $revision): bool
    {
        return $user->hasPermission('content.publish');
    }
}
