<?php

namespace Tests\Feature\Site;

use App\Enums\PostStatus;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Service;
use Database\Seeders\SiteSettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Technical SEO (NFR-SEO-03/04/05): sitemap.xml, robots.txt, structured
 * data, and noindex on error pages.
 */
class SeoTest extends SiteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SiteSettingsSeeder::class);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function jsonLd(string $html, string $type): ?array
    {
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches);

        foreach ($matches[1] as $block) {
            $decoded = json_decode($block, true);
            if (json_last_error() === JSON_ERROR_NONE && ($decoded['@type'] ?? null) === $type) {
                return $decoded;
            }
        }

        return null;
    }

    // Sitemap ------------------------------------------------------------

    public function test_sitemap_lists_exactly_the_published_urls_and_excludes_drafts(): void
    {
        Page::factory()->published()->create(['slug' => 'about']);
        Page::factory()->draft()->create(['slug' => 'privacy-policy']);
        Page::factory()->draft()->create(['slug' => 'terms-of-service']);

        $activeService = Service::factory()->create(['is_active' => true, 'slug' => 'active-service']);
        $inactiveService = Service::factory()->create(['is_active' => false, 'slug' => 'inactive-service']);

        $publishedPost = BlogPost::factory()->published()->create(['slug' => 'published-post']);
        $draftPost = BlogPost::factory()->draft()->create(['slug' => 'draft-post']);
        $reviewPost = BlogPost::factory()->inReview()->create(['slug' => 'review-post']);
        $scheduledPost = BlogPost::factory()->create([
            'status' => PostStatus::Published, 'published_at' => now()->addDays(3), 'slug' => 'not-due-yet',
        ]);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();

        // Always-on routes, regardless of any page record.
        foreach (['home', 'contact', 'services.index', 'consultants.index', 'core-values.index', 'insights.index'] as $route) {
            $this->assertStringContainsString('<loc>'.route($route).'</loc>', $xml, $route);
        }

        $this->assertStringContainsString('<loc>'.route('about').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('services.show', $activeService->slug).'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('insights.show', $publishedPost->slug).'</loc>', $xml);

        foreach (['privacy-policy', 'terms-of-service'] as $route) {
            $this->assertStringNotContainsString(route($route), $xml, $route);
        }
        $this->assertStringNotContainsString(route('services.show', $inactiveService->slug), $xml);
        foreach ([$draftPost, $reviewPost, $scheduledPost] as $post) {
            $this->assertStringNotContainsString(route('insights.show', $post->slug), $xml, $post->slug);
        }
    }

    public function test_sitemap_is_valid_xml_and_cached_for_a_short_ttl(): void
    {
        $response = $this->get(route('sitemap'))->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $doc = new \DOMDocument;
        $this->assertTrue($doc->loadXML($response->getContent()), 'sitemap.xml must be well-formed XML');
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $response->getContent());

        // Cached: content added after the first request doesn't appear yet.
        BlogPost::factory()->published()->create(['slug' => 'added-after-first-request']);
        $second = $this->get(route('sitemap'))->getContent();
        $this->assertStringNotContainsString('added-after-first-request', $second);

        Cache::forget('sitemap.xml');
        $third = $this->get(route('sitemap'))->getContent();
        $this->assertStringContainsString('added-after-first-request', $third);
    }

    public function test_sitemap_entries_have_a_lastmod_when_a_record_backs_them(): void
    {
        $post = BlogPost::factory()->published()->create(['slug' => 'dated-post', 'updated_at' => '2026-03-01 12:00:00']);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertStringContainsString('<lastmod>2026-03-01T12:00:00+00:00</lastmod>', $xml);
    }

    // Robots.txt -----------------------------------------------------------

    public function test_robots_txt_disallows_admin_and_points_to_the_sitemap(): void
    {
        $response = $this->get('/robots.txt')->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $text = $response->getContent();
        $this->assertStringContainsString('Disallow: /admin', $text);
        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $text);
        $this->assertStringNotContainsString('Disallow: /services', $text);
        $this->assertStringNotContainsString('Disallow: /insights', $text);
    }

    // Structured data --------------------------------------------------------

    public function test_organization_schema_is_present_and_valid_on_every_page_type(): void
    {
        Page::factory()->published()->create(['slug' => 'about']);

        foreach (['/', '/about', '/services', '/consultants', '/core-values', '/insights', '/contact'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $schema = $this->jsonLd($html, 'Organization');

            $this->assertNotNull($schema, "Organization schema missing on {$url}");
            $this->assertArrayHasKey('name', $schema);
            $this->assertArrayHasKey('url', $schema);
        }
    }

    public function test_service_schema_is_present_and_valid_on_a_service_page(): void
    {
        $service = Service::factory()->create([
            'title' => 'Energy Policy Evaluations', 'slug' => 'energy-policy-evaluations', 'description' => 'Full description.',
        ]);

        $html = $this->get(route('services.show', $service->slug))->assertOk()->getContent();
        $schema = $this->jsonLd($html, 'Service');

        $this->assertNotNull($schema);
        $this->assertSame('Energy Policy Evaluations', $schema['name']);
        $this->assertSame('Full description.', $schema['description']);
        $this->assertSame(route('services.show', $service->slug), $schema['url']);
        $this->assertSame('Organization', $schema['provider']['@type']);
    }

    public function test_article_schema_is_present_and_valid_json_on_a_post(): void
    {
        $post = BlogPost::factory()->published()->create(['title' => 'A Governance Article']);

        $html = $this->get(route('insights.show', $post->slug))->assertOk()->getContent();
        $schema = $this->jsonLd($html, 'Article');

        $this->assertNotNull($schema);
        $this->assertSame('A Governance Article', $schema['headline']);
    }

    // Error pages: noindex ---------------------------------------------------

    public function test_429_page_is_marked_noindex(): void
    {
        Notification::fake();

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('contact.store'), [
                'name' => 'A', 'email' => 'a@example.com', 'subject' => 'S', 'message' => 'M', 'consent' => '1',
            ]);
        }

        $response = $this->post(route('contact.store'), [
            'name' => 'A', 'email' => 'a@example.com', 'subject' => 'S', 'message' => 'M', 'consent' => '1',
        ])->assertStatus(429);

        $response->assertSee('<meta name="robots" content="noindex">', false);
        $response->assertDontSee('rel="canonical"', false);
    }

    public function test_no_custom_500_page_exists_a_5xx_status_is_inherently_non_indexable(): void
    {
        // Confirmed rather than assumed (item 4): there is no custom 500
        // view, so nothing needs a noindex tag added to it. A bare 5xx HTTP
        // status is not crawled/indexed regardless of body content, so this
        // is not a gap — if a custom 500 view is added later it must carry
        // the same @section('robots', 'noindex') as 404 and 429.
        $this->assertFileDoesNotExist(resource_path('views/errors/500.blade.php'));
    }
}
