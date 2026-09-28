<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\EditOrManage;

class ServicePolicy
{
    use EditOrManage;

    /**
     * Lead/supporting consultant assignment (FR-ADM-05) is structural, so
     * manage-level, like ConsultantPolicy::assignServices.
     */
    public function assignConsultants(User $user): bool
    {
        return $this->canManage($user);
    }

    protected function area(): string
    {
        return 'services';
    }
}
