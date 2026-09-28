<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Consultant;
use App\Models\CoreValue;
use App\Models\Page;
use App\Models\Service;
use App\Support\SiteSettings;
use App\View\Presenters\ContentPresenter;
use Illuminate\Contracts\View\View;

/**
 * CMS-managed pages. Home and contact always render (the site and the
 * contact form must never 404); their `page` is null while the CMS page is
 * unpublished. About and the legal pages 404 until published.
 */
class PageController extends Controller
{
    /** Consultants featured on the home page (FR-HOME-05). */
    private const HOME_CONSULTANTS = 4;

    /** Insights featured on the home page (FR-HOME-06). */
    private const HOME_INSIGHTS = 3;

    public function __construct(private ContentPresenter $presenter) {}

    public function home(): View
    {
        $page = $this->publishedPage('home');
        $presented = $page === null ? null : $this->presenter->page($page, route('home'));

        return view('public.home', [
            'page' => $presented,
            'services' => Service::query()->active()->ordered()->get()
                ->map(fn (Service $s) => $this->presenter->serviceSummary($s))->all(),
            'coreValues' => CoreValue::query()->active()->ordered()->with('icon')->get()
                ->map(fn (CoreValue $v) => $this->presenter->coreValue($v))->all(),
            'consultants' => Consultant::query()->active()->ordered()->with('photo')
                ->limit(self::HOME_CONSULTANTS)->get()
                ->map(fn (Consultant $c) => $this->presenter->consultant($c))->all(),
            'insights' => BlogPost::query()->published()->with(InsightController::SUMMARY_RELATIONS)
                ->latest('published_at')->limit(self::HOME_INSIGHTS)->get()
                ->map(fn (BlogPost $p) => $this->presenter->postSummary($p))->all(),
            'seo' => $presented['seo']
                ?? $this->presenter->seo(app(SiteSettings::class)->get('seo.default_title'), canonicalUrl: route('home')),
        ]);
    }

    public function about(): View
    {
        $page = $this->presenter->page($this->publishedPageOrFail('about'), route('about'));

        return view('public.about', [
            'page' => $page,
            // Leadership / consultant overview (FR-ABOUT-04).
            'consultants' => Consultant::query()->active()->ordered()->with('photo')->get()
                ->map(fn (Consultant $c) => $this->presenter->consultant($c))->all(),
            'seo' => $page['seo'],
        ]);
    }

    public function contact(): View
    {
        $page = $this->publishedPage('contact');
        $presented = $page === null ? null : $this->presenter->page($page, route('contact'));

        return view('public.contact', [
            'page' => $presented,
            'seo' => $presented['seo'] ?? $this->presenter->seo(
                $this->presenter->pageTitle('Contact'),
                canonicalUrl: route('contact'),
            ),
        ]);
    }

    public function privacyPolicy(): View
    {
        return $this->legal('privacy-policy');
    }

    public function termsOfService(): View
    {
        return $this->legal('terms-of-service');
    }

    private function legal(string $slug): View
    {
        $page = $this->presenter->page($this->publishedPageOrFail($slug), route($slug));

        return view("public.{$slug}", ['page' => $page, 'seo' => $page['seo']]);
    }

    private function publishedPage(string $slug): ?Page
    {
        return Page::query()->published()->where('slug', $slug)->first();
    }

    private function publishedPageOrFail(string $slug): Page
    {
        return Page::query()->published()->where('slug', $slug)->firstOrFail();
    }
}
