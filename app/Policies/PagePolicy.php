<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\EditOrManage;

/**
 * Pages have no §7 row; mapped like services (plan §6 rows 15–16, D11).
 */
class PagePolicy
{
    use EditOrManage;

    public function publish(User $user): bool
    {
        return $user->hasPermission('content.publish');
    }

    protected function area(): string
    {
        return 'pages';
    }
}
