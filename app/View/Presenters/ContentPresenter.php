<?php

namespace App\View\Presenters;

use App\Models\BlogPost;
use App\Models\Consultant;
use App\Models\CoreValue;
use App\Models\Media;
use App\Models\Page;
use App\Models\Service;
use App\Support\SafeUrl;
use App\Support\SiteSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns models into the plain arrays documented in docs/frontend-contract.md.
 * Views receive only these arrays, never models, so the contract is exact,
 * nothing private (e.g. author emails) leaks, and a view can't trigger a
 * query. Callers must eager load every relation used here.
 */
class ContentPresenter
{
    /**
     * Settings are resolved per call, never held: controllers (and this
     * presenter inside them) are cached on their route, so a stored copy
     * would go stale under a long-running worker.
     */
    private function settings(): SiteSettings
    {
        return app(SiteSettings::class);
    }

    /**
     * SEO block for any public page (NFR-SEO-01/03/04).
     *
     * @param  array<string, mixed>|null  $ogImage
     * @return array{title: string, description: ?string, canonical_url: string, og_image: ?array}
     */
    public function seo(?string $title, ?string $description = null, ?string $canonicalUrl = null, ?array $ogImage = null): array
    {
        $siteName = (string) $this->settings()->get('branding.site_name', config('app.name'));

        return [
            'title' => $title === null || $title === '' ? $siteName : $title,
            'description' => $description ?: $this->settings()->get('seo.default_description'),
            'canonical_url' => $canonicalUrl ?: url()->current(),
            'og_image' => $ogImage ?? $this->settings()->get('seo.default_og_image'),
        ];
    }

    /**
     * Title for listing pages: "Services | InspAya Consult".
     */
    public function pageTitle(string $title): string
    {
        return $title.' | '.$this->settings()->get('branding.site_name', config('app.name'));
    }

    /**
     * @return array<string, mixed>
     */
    public function page(Page $page, string $url): array
    {
        $sections = $this->sections($page->structured_content ?? []);
        $firstImage = collect($sections)
            ->flatMap(fn (array $section) => collect($section['data'])
                ->filter(fn ($value, $key) => is_array($value) && str_ends_with((string) $key, '_media'))
                ->values())
            ->first();

        return [
            'title' => $page->title,
            'slug' => $page->slug,
            'url' => $url,
            'sections' => $sections,
            'published_at' => $this->isoDate($page->published_at),
            'published_on' => $this->displayDate($page->published_at),
            'seo' => $this->seo(
                $page->meta_title ?: $this->pageTitle($page->title),
                $page->meta_description,
                $page->canonical_url ?: $url,
                // The page's own sharing image, else the first section image,
                // else (inside seo()) the site default.
                MediaPresenter::present($page->ogImage) ?? $firstImage,
            ),
        ];
    }

    /**
     * @return array{title: string, slug: string, url: string, short_description: ?string}
     */
    public function serviceSummary(Service $service): array
    {
        return [
            'title' => $service->title,
            'slug' => $service->slug,
            'url' => route('services.show', $service->slug),
            'short_description' => $service->short_description,
        ];
    }

    /**
     * Requires consultants (active only) with photo loaded.
     *
     * @return array<string, mixed>
     */
    public function serviceDetail(Service $service): array
    {
        $consultants = $service->consultants->map(fn (Consultant $consultant) => $this->consultant($consultant) + [
            'is_lead' => (bool) $consultant->pivot->is_lead,
        ])->values();

        $url = route('services.show', $service->slug);

        return $this->serviceSummary($service) + [
            'description' => $service->description,
            'capabilities' => array_values($service->capabilities ?? []),
            'outcomes' => array_values($service->outcomes ?? []),
            'consultants' => $consultants->all(),
            'lead_consultants' => $consultants->where('is_lead', true)->values()->all(),
            'seo' => $this->seo(
                $service->meta_title ?: $this->pageTitle($service->title),
                $service->meta_description ?: $service->short_description,
                $url,
            ),
        ];
    }

    /**
     * Requires photo loaded; services are included only when loaded.
     *
     * @return array<string, mixed>
     */
    public function consultant(Consultant $consultant): array
    {
        $data = [
            'name' => $consultant->name,
            'title' => $consultant->title,
            'bio' => $consultant->bio,
            'photo' => MediaPresenter::present($consultant->photo),
            'expertise' => array_values($consultant->expertise ?? []),
            'qualifications' => array_values($consultant->qualifications ?? []),
            'email' => $consultant->email,
            'links' => array_filter(array_map(SafeUrl::sanitize(...), $consultant->links ?? [])),
        ];

        if ($consultant->relationLoaded('services')) {
            $data['services'] = $consultant->services
                ->map(fn (Service $service) => [
                    'title' => $service->title,
                    'url' => route('services.show', $service->slug),
                ])
                ->values()
                ->all();
        }

        return $data;
    }

    /**
     * Requires icon loaded.
     *
     * @return array{title: string, slug: string, description: ?string, icon: ?array}
     */
    public function coreValue(CoreValue $value): array
    {
        return [
            'title' => $value->title,
            'slug' => $value->slug,
            'description' => $value->description,
            'icon' => MediaPresenter::present($value->icon),
        ];
    }

    /**
     * Requires author, category, featuredImage and tags loaded.
     *
     * @return array<string, mixed>
     */
    public function postSummary(BlogPost $post): array
    {
        return [
            'title' => $post->title,
            'slug' => $post->slug,
            'url' => route('insights.show', $post->slug),
            'excerpt' => $post->excerpt,
            'published_at' => $this->isoDate($post->published_at),
            'published_on' => $this->displayDate($post->published_at),
            // Name only: never expose the author's email or account details.
            'author' => ['name' => $post->author->name],
            'category' => $post->category === null ? null : [
                'name' => $post->category->name,
                'slug' => $post->category->slug,
            ],
            'tags' => $post->tags
                ->map(fn ($tag) => ['name' => $tag->name, 'slug' => $tag->slug])
                ->values()
                ->all(),
            'featured_image' => MediaPresenter::present($post->featuredImage),
        ];
    }

    /**
     * @param  Collection<int, BlogPost>  $related  loaded like postSummary()
     * @return array<string, mixed>
     */
    public function postDetail(BlogPost $post, Collection $related): array
    {
        $summary = $this->postSummary($post);

        return $summary + [
            'content' => $post->content,
            'related' => $related->map(fn (BlogPost $p) => $this->postSummary($p))->values()->all(),
            'seo' => $this->seo(
                $post->meta_title ?: $this->pageTitle($post->title),
                $post->meta_description ?: $post->excerpt,
                $post->canonical_url ?: $summary['url'],
                $summary['featured_image'],
            ),
        ];
    }

    /**
     * Page blocks with every "<name>_media_id" replaced by "<name>_media"
     * (a MediaPresenter array, or null when the id is missing or dangling:
     * these ids are not foreign keys). One query for all ids on the page.
     *
     * @param  array<int, mixed>  $blocks
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function sections(array $blocks): array
    {
        // Collected per block: several blocks can use the same key name.
        $ids = collect($blocks)
            ->flatMap(fn ($block) => collect((array) ($block['data'] ?? []))
                ->filter(fn ($value, $key) => str_ends_with((string) $key, '_media_id') && is_numeric($value))
                ->values())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $media = $ids->isEmpty() ? collect() : Media::query()->whereKey($ids->all())->get()->keyBy('id');

        return collect($blocks)
            ->filter(fn ($block) => is_array($block) && isset($block['type']))
            ->map(function (array $block) use ($media) {
                $data = [];

                foreach ((array) ($block['data'] ?? []) as $key => $value) {
                    if (str_ends_with((string) $key, '_media_id')) {
                        $data[Str::beforeLast($key, '_id')] = MediaPresenter::present(
                            is_numeric($value) ? $media->get((int) $value) : null
                        );

                        continue;
                    }

                    $data[$key] = $this->sanitizeUrls($value);
                }

                return ['type' => (string) $block['type'], 'data' => $data];
            })
            ->values()
            ->all();
    }

    /**
     * Any "url" key inside section data (e.g. call-to-action links) is
     * CMS-entered, so unsafe schemes are dropped (see SafeUrl).
     */
    private function sanitizeUrls(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $key === 'url' ? SafeUrl::sanitize($item) : $this->sanitizeUrls($item);
        }

        return $value;
    }

    private function isoDate(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601String();
    }

    private function displayDate(?CarbonInterface $date): ?string
    {
        return $date?->format('j F Y');
    }
}
