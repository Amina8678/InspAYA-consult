<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Editing roles and their permissions is Super Admin only (plan §6 row 7).
 * Administrators can list roles so they can assign them. The Super Admin role
 * itself is never edited or deleted, so it can't lose its permissions.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.manage') || $user->hasPermission('users.assign-role');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.manage') && $role->slug !== 'super-admin';
    }

    /**
     * Roles still held by users are also refused by the database
     * (users.role_id RESTRICT).
     */
    public function delete(User $user, Role $role): bool
    {
        return $this->update($user, $role) && ! $role->users()->exists();
    }
}
