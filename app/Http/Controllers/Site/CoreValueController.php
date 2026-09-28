<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CoreValue;
use App\View\Presenters\ContentPresenter;
use Illuminate\Contracts\View\View;

class CoreValueController extends Controller
{
    public function __construct(private ContentPresenter $presenter) {}

    public function index(): View
    {
        return view('public.core-values.index', [
            'coreValues' => CoreValue::query()->active()->ordered()->with('icon')->get()
                ->map(fn (CoreValue $v) => $this->presenter->coreValue($v))->all(),
            'seo' => $this->presenter->seo(
                $this->presenter->pageTitle('Core Values'),
                canonicalUrl: route('core-values.index'),
            ),
        ]);
    }
}
