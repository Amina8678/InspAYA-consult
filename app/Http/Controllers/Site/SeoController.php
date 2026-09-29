<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Service;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Technical SEO endpoints (NFR-SEO-03): sitemap.xml and robots.txt. Built as
 * plain strings, not Blade views: a `.blade.php` file starting with the
 * literal `<?xml` declaration risks PHP misreading it as a short open tag,
 * and hand-built XML needs XML-safe escaping (ENT_XML1), not HTML escaping.
 */
class SeoController extends Controller
{
    /**
     * How long a generated sitemap is served from cache before the URL list
     * is rebuilt. Chosen over per-request generation because sitemaps are
     * fetched repeatedly by crawlers in bursts; chosen over a long TTL so a
     * newly published page is discoverable again within minutes. There is no
     * proactive cache-busting on publish/unpublish — this TTL is the only
     * bound on staleness, which keeps this endpoint decoupled from every
     * content controller.
     */
    public const CACHE_SECONDS = 300;

    public function sitemap(): Response
    {
        $urls = Cache::remember('sitemap.xml', self::CACHE_SECONDS, fn () => $this->urls());

        return response($this->render($urls), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Every published page-, service-, post- and consultant-bearing route
     * (FR-VAL's core values page is included too, for the same reason: it is
     * real indexable content with no draft state to exclude). Excludes
     * drafts, inactive services, and CMS pages that 404 while unpublished.
     *
     * @return list<array{url: string, lastmod: ?string}>
     */
    private function urls(): array
    {
        $urls = [];
        $publishedPages = Page::query()->published()->get(['slug', 'updated_at'])->keyBy('slug');

        // Always 200, whether or not a page has been published for them.
        foreach (['home', 'contact'] as $slug) {
            $urls[] = ['url' => route($slug), 'lastmod' => $this->lastmod($publishedPages->get($slug)?->updated_at)];
        }

        // 404 until published (frontend-contract.md §2).
        foreach (['about', 'privacy-policy', 'terms-of-service'] as $slug) {
            if ($page = $publishedPages->get($slug)) {
                $urls[] = ['url' => route($slug), 'lastmod' => $this->lastmod($page->updated_at)];
            }
        }

        $urls[] = ['url' => route('services.index'), 'lastmod' => null];
        foreach (Service::query()->active()->get(['slug', 'updated_at']) as $service) {
            $urls[] = ['url' => route('services.show', $service->slug), 'lastmod' => $this->lastmod($service->updated_at)];
        }

        $urls[] = ['url' => route('consultants.index'), 'lastmod' => null];
        $urls[] = ['url' => route('core-values.index'), 'lastmod' => null];

        $urls[] = ['url' => route('insights.index'), 'lastmod' => null];
        foreach (BlogPost::query()->published()->get(['slug', 'updated_at']) as $post) {
            $urls[] = ['url' => route('insights.show', $post->slug), 'lastmod' => $this->lastmod($post->updated_at)];
        }

        return $urls;
    }

    private function lastmod(mixed $date): ?string
    {
        return $date?->toAtomString();
    }

    /**
     * @param  list<array{url: string, lastmod: ?string}>  $urls
     */
    private function render(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $entry) {
            $xml .= "    <url>\n";
            $xml .= '        <loc>'.$this->escape($entry['url'])."</loc>\n";
            if ($entry['lastmod'] !== null) {
                $xml .= '        <lastmod>'.$this->escape($entry['lastmod'])."</lastmod>\n";
            }
            $xml .= "    </url>\n";
        }

        $xml .= '</urlset>'."\n";

        return $xml;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
