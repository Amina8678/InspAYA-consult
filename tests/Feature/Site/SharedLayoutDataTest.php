<?php

namespace Tests\Feature\Site;

use App\Models\BlogPost;
use App\Models\Consultant;
use App\Models\CoreValue;
use App\Models\Media;
use App\Models\Page;
use App\Models\Service;
use App\Models\SiteSetting;
use Database\Seeders\SiteSettingsSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class SharedLayoutDataTest extends SiteTestCase
{
    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function publicRoutes(): array
    {
        return [
            'home' => ['home', []],
            'services' => ['services.index', []],
            'consultants' => ['consultants.index', []],
            'core values' => ['core-values.index', []],
            'insights' => ['insights.index', []],
            'contact' => ['contact', []],
        ];
    }

    #[DataProvider('publicRoutes')]
    public function test_every_public_view_receives_settings_navigation_and_footer(string $route): void
    {
        $this->seed(SiteSettingsSeeder::class);

        $this->get(route($route))->assertOk()
            ->assertViewHas('settings', fn (array $settings) => $settings['contact']['email'] === 'hello@example.com')
            ->assertViewHas('navigation', fn (array $nav) => $nav[0]['label'] === 'Home')
            ->assertViewHas('footer', fn (array $footer) => $footer['contact']['email'] === 'hello@example.com');
    }

    public function test_settings_are_a_plain_nested_array_with_typed_values(): void
    {
        $this->seed(SiteSettingsSeeder::class);
        $logo = Media::factory()->create();
        SiteSetting::where('key', 'branding.logo')->update(['media_id' => $logo->id]);

        $this->get(route('home'))->assertViewHas('settings', function (array $settings) use ($logo) {
            $this->assertSame('InspAya Consult', $settings['branding']['site_name']);
            $this->assertStringEndsWith($logo->storage_path, $settings['branding']['logo']['url']);
            $this->assertNull($settings['branding']['footer_logo']);
            $this->assertNull($settings['social']['linkedin']);

            return true;
        });
    }

    public function test_navigation_follows_the_site_map_and_marks_the_active_item(): void
    {
        $service = Service::factory()->create(['title' => 'Governance']);

        $this->get(route('services.show', $service->slug))->assertViewHas('navigation', function (array $nav) {
            $this->assertSame(['Home', 'Services', 'Consultants', 'Core Values', 'Insights', 'Contact'], array_column($nav, 'label'));
            $services = $nav[1];
            $this->assertTrue($services['active']);
            $this->assertFalse($nav[0]['active']);
            $this->assertSame('Governance', $services['children'][0]['label']);
            $this->assertTrue($services['children'][0]['active']);

            return true;
        });
    }

    public function test_about_and_legal_links_appear_only_once_published(): void
    {
        Page::factory()->published()->create(['slug' => 'about', 'title' => 'About']);
        Page::factory()->published()->create(['slug' => 'privacy-policy', 'title' => 'Privacy Policy']);
        Page::factory()->draft()->create(['slug' => 'terms-of-service']);

        $this->get(route('home'))
            ->assertViewHas('navigation', fn (array $nav) => array_column($nav, 'label')[1] === 'About')
            ->assertViewHas('footer', fn (array $footer) => array_column($footer['legal'], 'label') === ['Privacy Policy']);
    }

    public function test_footer_social_links_skip_empty_networks(): void
    {
        $this->seed(SiteSettingsSeeder::class);
        SiteSetting::where('key', 'social.linkedin')->update(['value' => 'https://example.com/company']);

        $this->get(route('home'))->assertViewHas('footer', fn (array $footer) => $footer['social'] === [
            ['network' => 'linkedin', 'url' => 'https://example.com/company'],
        ]);
    }

    public function test_site_settings_are_queried_once_per_request(): void
    {
        $this->seed(SiteSettingsSeeder::class);
        $queries = $this->captureQueries(fn () => $this->get(route('home'))->assertOk());

        $this->assertCount(1, array_filter($queries, fn (string $sql) => str_contains($sql, 'from "site_settings"')
            || str_contains($sql, 'from `site_settings`')));
    }

    public function test_insights_index_query_count_does_not_grow_with_posts(): void
    {
        BlogPost::factory()->published()->categorised()->withFeaturedImage()->count(2)->create();
        $few = count($this->captureQueries(fn () => $this->get(route('insights.index'))->assertOk()));

        BlogPost::factory()->published()->categorised()->withFeaturedImage()->count(7)->create()
            ->each(fn (BlogPost $post) => $post->tags()->attach(\App\Models\Tag::factory()->create()));
        $many = count($this->captureQueries(fn () => $this->get(route('insights.index'))->assertOk()));

        $this->assertSame($few, $many);
    }

    public function test_home_query_count_does_not_grow_with_content(): void
    {
        Service::factory()->count(2)->create();
        CoreValue::factory()->withIcon()->count(2)->create();
        Consultant::factory()->withPhoto()->count(2)->create();
        BlogPost::factory()->published()->withFeaturedImage()->count(2)->create();
        $few = count($this->captureQueries(fn () => $this->get(route('home'))->assertOk()));

        Service::factory()->count(5)->create();
        CoreValue::factory()->withIcon()->count(4)->create();
        Consultant::factory()->withPhoto()->count(4)->create();
        BlogPost::factory()->published()->withFeaturedImage()->count(4)->create();
        $many = count($this->captureQueries(fn () => $this->get(route('home'))->assertOk()));

        $this->assertSame($few, $many);
    }

    /**
     * @return list<string>
     */
    private function captureQueries(callable $callback): array
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        // A fresh request scope: scoped singletons (settings, composer) reset.
        $this->app->forgetScopedInstances();
        $callback();

        DB::getEventDispatcher()->forget('Illuminate\Database\Events\QueryExecuted');

        return $queries;
    }
}
