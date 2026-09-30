<?php

namespace App\Policies;

use App\Policies\Concerns\EditOrManage;

class FaqPolicy
{
    use EditOrManage;

    protected function area(): string
    {
        return 'faqs';
    }
}
