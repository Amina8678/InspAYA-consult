<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;

/**
 * Permissions are defined in code (RolesAndPermissionsSeeder); the CMS only
 * lists them for role editing.
 */
class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Permission $permission): bool
    {
        return false;
    }

    public function delete(User $user, Permission $permission): bool
    {
        return false;
    }
}
