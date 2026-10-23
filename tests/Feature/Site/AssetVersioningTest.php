<?php

namespace Tests\Feature\Site;

use App\Support\Assets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_url_carries_a_version_derived_from_the_file_mtime(): void
    {
        $mtime = filemtime(public_path('css/site.css'));

        $this->assertSame(asset('css/site.css').'?v='.base_convert((string) $mtime, 10, 36), Assets::url('css/site.css'));
    }

    public function test_the_version_changes_when_the_file_changes(): void
    {
        $file = public_path('css/_version_probe.css');
        file_put_contents($file, 'a{}');
        try {
            touch($file, 1_700_000_000);
            $first = Assets::url('css/_version_probe.css');
            touch($file, 1_700_000_100);
            clearstatcache();
            $second = Assets::url('css/_version_probe.css');
        } finally {
            unlink($file);
        }

        $this->assertNotSame($first, $second);
    }

    public function test_a_missing_file_gets_a_plain_url_and_a_leading_slash_is_ignored(): void
    {
        $this->assertSame(asset('css/nope.css'), Assets::url('css/nope.css'));
        $this->assertSame(Assets::url('css/site.css'), Assets::url('/css/site.css'));
    }

    public function test_public_pages_version_their_stylesheets_and_script(): void
    {
        $html = $this->get('/contact')->assertOk()->getContent();

        foreach (['css/theme.css', 'css/site.css', 'js/site.js'] as $path) {
            $this->assertStringContainsString(e(Assets::url($path)), $html);
            $this->assertMatchesRegularExpression('#'.preg_quote($path, '#').'\?v=[0-9a-z]+#', $html);
        }
    }

    public function test_the_admin_login_versions_its_assets(): void
    {
        $html = $this->get(route('admin.login'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#admin-assets/admin\.css\?v=[0-9a-z]+#', $html);
        $this->assertMatchesRegularExpression('#admin-assets/admin\.js\?v=[0-9a-z]+#', $html);
    }
}
