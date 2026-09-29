<?php

namespace App\View\Composers;

use App\Models\Page;
use App\Models\Service;
use App\Support\SafeUrl;
use App\Support\SiteSettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Shared data for every `public.*` view: $settings, $navigation, $footer.
 *
 * Registered as a scoped singleton, so its queries run once per request no
 * matter how many layouts and partials are rendered.
 */
class SiteLayoutComposer
{
    /** @var array<string, mixed>|null */
    private ?array $shared = null;

    public function __construct(private SiteSettings $settings) {}

    public function compose(View $view): void
    {
        $view->with($this->shared ??= $this->build());
    }

    /**
     * @return array{settings: array<string, mixed>, navigation: list<array<string, mixed>>, footer: array<string, mixed>}
     */
    private function build(): array
    {
        $settings = $this->settings->all();

        $services = Service::query()->active()->ordered()
            ->get(['id', 'title', 'slug'])
            ->map(fn (Service $service) => [
                'label' => $service->title,
                'url' => route('services.show', $service->slug),
                'active' => request()->routeIs('services.show') && request()->route('slug') === $service->slug,
            ])
            ->all();

        // One query for the optional pages linked from the layout; unpublished
        // ones are left out so no link leads to a 404.
        $published = Page::query()->published()
            ->whereIn('slug', ['about', 'privacy-policy', 'terms-of-service'])
            ->pluck('title', 'slug');

        return [
            'settings' => $settings,
            'navigation' => $this->navigation($services, $published->has('about')),
            'footer' => [
                'site_name' => Arr::get($settings, 'branding.site_name'),
                'logo' => Arr::get($settings, 'branding.footer_logo'),
                'image' => Arr::get($settings, 'branding.footer_image'),
                'contact' => [
                    'email' => Arr::get($settings, 'contact.email'),
                    'phone' => Arr::get($settings, 'contact.phone'),
                    'address' => Arr::get($settings, 'contact.address'),
                ],
                'social' => collect(Arr::get($settings, 'social', []))
                    ->map(SafeUrl::sanitize(...))
                    ->filter()
                    ->map(fn ($url, $network) => ['network' => $network, 'url' => $url])
                    ->values()
                    ->all(),
                'services' => array_map(fn ($s) => ['label' => $s['label'], 'url' => $s['url']], $services),
                'legal' => $published->only(['privacy-policy', 'terms-of-service'])
                    ->map(fn ($title, $slug) => ['label' => $title, 'url' => route($slug)])
                    ->values()
                    ->all(),
                'year' => (int) now()->format('Y'),
            ],
        ];
    }

    /**
     * Main menu following the SRS Appendix A site map.
     *
     * @param  list<array<string, mixed>>  $services
     * @return list<array<string, mixed>>
     */
    private function navigation(array $services, bool $aboutPublished): array
    {
        $items = [
            ['label' => 'Home', 'route' => 'home'],
            ...($aboutPublished ? [['label' => 'About', 'route' => 'about']] : []),
            ['label' => 'Services', 'route' => 'services.index', 'children' => $services],
            ['label' => 'Consultants', 'route' => 'consultants.index'],
            ['label' => 'Core Values', 'route' => 'core-values.index'],
            ['label' => 'Insights', 'route' => 'insights.index'],
            ['label' => 'Contact', 'route' => 'contact'],
        ];

        return array_map(function (array $item) {
            $prefix = Str::beforeLast($item['route'], '.index');

            return [
                'label' => $item['label'],
                'url' => route($item['route']),
                'active' => request()->routeIs($item['route'], $prefix.'.*'),
                'children' => $item['children'] ?? [],
            ];
        }, $items);
    }
}
