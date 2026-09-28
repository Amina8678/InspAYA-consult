<?php

namespace App\Policies;

use App\Policies\Concerns\EditOrManage;

class ServicePolicy
{
    use EditOrManage;

    protected function area(): string
    {
        return 'services';
    }
}
