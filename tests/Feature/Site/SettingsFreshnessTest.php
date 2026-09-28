<?php

namespace Tests\Feature\Site;

use App\Models\SiteSetting;
use Database\Seeders\SiteSettingsSeeder;

/**
 * Controllers are cached on their route for the life of the process. Settings
 * must still be read fresh on every request, or a long-running worker would
 * keep serving the values from its first request.
 */
class SettingsFreshnessTest extends SiteTestCase
{
    public function test_a_changed_setting_is_used_on_the_next_request(): void
    {
        $this->seed(SiteSettingsSeeder::class);

        $this->get('/')->assertSee('<title>InspAya Consult</title>', false);

        SiteSetting::where('key', 'seo.default_title')->update(['value' => 'Changed title']);
        SiteSetting::where('key', 'seo.default_description')->update(['value' => 'Changed description']);
        $this->app->forgetScopedInstances(); // what a new request does

        $this->get('/')
            ->assertSee('<title>Changed title</title>', false)
            ->assertSee('content="Changed description"', false);
    }
}
