<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Categories and tags (plan §6 row 21): anyone who writes posts can list
 * them to pick from; only taxonomy.manage changes them.
 */
trait TaxonomyRules
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('taxonomy.manage') || $user->hasPermission('posts.create');
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('taxonomy.manage');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('taxonomy.manage');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('taxonomy.manage');
    }
}
