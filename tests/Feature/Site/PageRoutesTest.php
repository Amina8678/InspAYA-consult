<?php

namespace Tests\Feature\Site;

use App\Models\BlogPost;
use App\Models\Consultant;
use App\Models\CoreValue;
use App\Models\Media;
use App\Models\Page;
use App\Models\Service;
use Database\Seeders\SiteSettingsSeeder;
use PHPUnit\Framework\Attributes\DataProvider;

class PageRoutesTest extends SiteTestCase
{
    public function test_home_renders_with_its_sections_and_featured_content(): void
    {
        Page::factory()->published()->create(['slug' => 'home', 'title' => 'Home']);
        Service::factory()->count(2)->create();
        Service::factory()->inactive()->create(['title' => 'Hidden service']);
        CoreValue::factory()->count(2)->create();
        Consultant::factory()->count(5)->create();
        BlogPost::factory()->published()->count(4)->create();
        BlogPost::factory()->draft()->create();

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertViewIs('public.home')
            ->assertViewHas('page', fn (array $page) => $page['slug'] === 'home')
            ->assertViewHas('services', fn (array $services) => count($services) === 2
                && ! in_array('Hidden service', array_column($services, 'title'), true))
            ->assertViewHas('coreValues', fn (array $values) => count($values) === 2)
            ->assertViewHas('consultants', fn (array $consultants) => count($consultants) === 4)
            ->assertViewHas('insights', fn (array $insights) => count($insights) === 3);
        $this->assertSeo($response);
    }

    public function test_draft_home_page_still_renders_without_errors(): void
    {
        Page::factory()->draft()->create(['slug' => 'home']);
        $this->seed(SiteSettingsSeeder::class);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertViewIs('public.home')
            ->assertViewHas('page', null)
            ->assertViewHas('services', [])
            ->assertViewHas('seo', fn (array $seo) => $seo['title'] === 'InspAya Consult');
    }

    public function test_home_renders_with_no_home_page_record_at_all(): void
    {
        $this->get(route('home'))->assertOk()->assertViewHas('page', null);
    }

    public function test_page_sections_resolve_media_ids_and_null_dangling_ones(): void
    {
        $image = Media::factory()->create(['alt_text' => 'Hero image']);
        Page::factory()->published()->create([
            'slug' => 'home',
            'structured_content' => [
                ['type' => 'hero', 'data' => ['heading' => 'Hello', 'background_media_id' => $image->id]],
                ['type' => 'feature', 'data' => ['background_media_id' => 999_999]],
            ],
        ]);

        $this->get(route('home'))->assertOk()->assertViewHas('page', function (array $page) use ($image) {
            $hero = $page['sections'][0]['data'];

            $this->assertSame('Hello', $hero['heading']);
            $this->assertArrayNotHasKey('background_media_id', $hero);
            $this->assertStringEndsWith($image->storage_path, $hero['background_media']['url']);
            $this->assertSame('Hero image', $hero['background_media']['alt']);
            $this->assertNull($page['sections'][1]['data']['background_media']);
            $this->assertSame($hero['background_media'], $page['seo']['og_image']);

            return true;
        });
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function publishablePages(): array
    {
        return [
            'about' => ['about', 'about', 'public.about'],
            'privacy policy' => ['privacy-policy', 'privacy-policy', 'public.privacy-policy'],
            'terms of service' => ['terms-of-service', 'terms-of-service', 'public.terms-of-service'],
        ];
    }

    #[DataProvider('publishablePages')]
    public function test_published_page_renders(string $slug, string $route, string $view): void
    {
        Page::factory()->published()->create(['slug' => $slug, 'title' => 'Title of '.$slug]);

        $response = $this->get(route($route));

        $response->assertOk()
            ->assertViewIs($view)
            ->assertViewHas('page', fn (array $page) => $page['title'] === 'Title of '.$slug
                && $page['url'] === route($route));
        $this->assertSeo($response);
    }

    #[DataProvider('publishablePages')]
    public function test_draft_page_404s(string $slug, string $route): void
    {
        Page::factory()->draft()->create(['slug' => $slug]);

        $this->get(route($route))->assertNotFound();
    }

    #[DataProvider('publishablePages')]
    public function test_page_with_future_publish_date_404s(string $slug, string $route): void
    {
        Page::factory()->published()->create(['slug' => $slug, 'published_at' => now()->addDay()]);

        $this->get(route($route))->assertNotFound();
    }

    #[DataProvider('publishablePages')]
    public function test_missing_page_404s(string $slug, string $route): void
    {
        $this->get(route($route))->assertNotFound();
    }

    public function test_default_og_image_setting_is_used_when_a_page_has_no_image(): void
    {
        $this->seed(SiteSettingsSeeder::class);
        $image = Media::factory()->create();
        \App\Models\SiteSetting::where('key', 'seo.default_og_image')->update(['media_id' => $image->id]);

        $this->get(route('services.index'))->assertOk()
            ->assertViewHas('seo', fn (array $seo) => str_ends_with($seo['og_image']['url'], $image->storage_path));
    }

    public function test_contact_renders_without_a_cms_page(): void
    {
        $response = $this->get(route('contact'));

        $response->assertOk()->assertViewIs('public.contact')->assertViewHas('page', null);
        $this->assertSeo($response);
    }

    public function test_contact_includes_its_cms_page_when_published(): void
    {
        Page::factory()->published()->create(['slug' => 'contact', 'title' => 'Contact']);

        $this->get(route('contact'))->assertOk()
            ->assertViewHas('page', fn (array $page) => $page['slug'] === 'contact');
    }
}
