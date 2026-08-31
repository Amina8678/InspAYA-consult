<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingPlan extends Model
{
    protected $fillable = ['name', 'price', 'features', 'order'];
    protected $casts = ['features' => 'array'];
}
