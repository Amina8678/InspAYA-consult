<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Consultant;
use App\View\Presenters\ContentPresenter;
use Illuminate\Contracts\View\View;

class ConsultantController extends Controller
{
    public function __construct(private ContentPresenter $presenter) {}

    /**
     * Active consultants only (FR-TEAM-01), each with their active services.
     */
    public function index(): View
    {
        return view('public.consultants.index', [
            'consultants' => Consultant::query()->active()->ordered()
                ->with(['photo', 'services' => fn ($query) => $query->active()->ordered()])
                ->get()
                ->map(fn (Consultant $c) => $this->presenter->consultant($c))->all(),
            'seo' => $this->presenter->seo(
                $this->presenter->pageTitle('Consultants'),
                canonicalUrl: route('consultants.index'),
            ),
        ]);
    }
}
