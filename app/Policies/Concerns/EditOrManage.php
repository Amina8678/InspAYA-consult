<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * §7 "Manage services / values / team" (Full / Full / Edit / No), plan §6
 * rows 9–16: `<area>.edit` updates existing records; `<area>.manage`
 * creates, deletes, reorders and activates/deactivates them.
 */
trait EditOrManage
{
    abstract protected function area(): string;

    public function viewAny(User $user): bool
    {
        return $this->canEdit($user) || $this->canManage($user);
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * Content fields only; is_active and sort_order go through changeStatus()
     * and reorder().
     */
    public function update(User $user): bool
    {
        return $this->canEdit($user);
    }

    public function changeStatus(User $user): bool
    {
        return $this->canManage($user);
    }

    public function reorder(User $user): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user): bool
    {
        return $this->canManage($user);
    }

    protected function canEdit(User $user): bool
    {
        return $user->hasPermission($this->area().'.edit');
    }

    protected function canManage(User $user): bool
    {
        return $user->hasPermission($this->area().'.manage');
    }
}
