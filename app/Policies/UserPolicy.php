<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * §7 "Manage users and roles" (Full / Limited / No / No) and FR-ADM-11.
 * Administrator's "Limited": cannot act on a Super Admin account or grant the
 * Super Admin role. Nobody changes their own role or deactivates themselves
 * (no accidental lock-out). Accounts are deactivated, never deleted.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.update') && $this->mayActOn($actor, $target);
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.deactivate')
            && $this->mayActOn($actor, $target)
            && ! $actor->is($target);
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.reset-password') && $this->mayActOn($actor, $target);
    }

    /**
     * Also check this when creating a user with a role.
     */
    public function assignRole(User $actor, User $target, Role $role): bool
    {
        return $actor->hasPermission('users.assign-role')
            && $this->mayActOn($actor, $target)
            && ! $actor->is($target)
            && ($role->slug !== 'super-admin' || $actor->isSuperAdmin());
    }

    public function delete(User $actor, User $target): bool
    {
        return false;
    }

    private function mayActOn(User $actor, User $target): bool
    {
        return ! $target->isSuperAdmin() || $actor->isSuperAdmin();
    }
}
