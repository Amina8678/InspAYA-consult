<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\View\Presenters\ContentPresenter;
use Illuminate\Contracts\View\View;

class ServiceController extends Controller
{
    public function __construct(private ContentPresenter $presenter) {}

    public function index(): View
    {
        return view('public.services.index', [
            'services' => Service::query()->active()->ordered()->get()
                ->map(fn (Service $s) => $this->presenter->serviceSummary($s))->all(),
            'seo' => $this->presenter->seo(
                $this->presenter->pageTitle('Services'),
                canonicalUrl: route('services.index'),
            ),
        ]);
    }

    /**
     * Inactive and unknown services 404 (FR-SVC-02).
     */
    public function show(string $slug): View
    {
        $service = Service::query()->active()->where('slug', $slug)
            ->with(['consultants' => fn ($query) => $query->active()->with('photo')])
            ->firstOrFail();

        $data = $this->presenter->serviceDetail($service);

        return view('public.services.show', ['service' => $data, 'seo' => $data['seo']]);
    }
}
