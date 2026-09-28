<?php

namespace App\Policies;

use App\Policies\Concerns\EditOrManage;

class CoreValuePolicy
{
    use EditOrManage;

    protected function area(): string
    {
        return 'values';
    }
}
