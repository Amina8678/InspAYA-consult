<?php

namespace App\Policies;

use App\Policies\Concerns\EditOrManage;

class TestimonialPolicy
{
    use EditOrManage;

    protected function area(): string
    {
        return 'testimonials';
    }
}
