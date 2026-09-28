<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * §7 "View audit logs" (Full / Full / No / No). The log is append-only
 * (NFR-SEC-07): nobody edits or deletes entries, whatever their role.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('audit-logs.view');
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $user->hasPermission('audit-logs.view');
    }

    public function update(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $log): bool
    {
        return false;
    }
}
