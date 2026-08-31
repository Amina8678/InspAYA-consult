<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\TeamMember;
use App\Models\Project;
use App\Models\BlogPost;
use App\Models\PricingPlan;

class DashboardController extends Controller
{
    public function index()
    {
        $counts = [
            'services' => Service::count(),
            'team'     => TeamMember::count(),
            'projects' => Project::count(),
            'blog'     => BlogPost::count(),
            'pricing'  => PricingPlan::count(),
        ];

        return view('admin.dashboard', compact('counts'));
    }
}
