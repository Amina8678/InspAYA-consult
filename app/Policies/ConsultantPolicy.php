<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\EditOrManage;

class ConsultantPolicy
{
    use EditOrManage;

    /**
     * Service assignment (FR-ADM-07) is structural, so manage-level.
     */
    public function assignServices(User $user): bool
    {
        return $this->canManage($user);
    }

    protected function area(): string
    {
        return 'consultants';
    }
}
