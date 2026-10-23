<?php

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_icon_files_are_real_not_empty(): void
    {
        $ico = file_get_contents(public_path('favicon.ico'));
        $this->assertGreaterThan(100, strlen($ico));
        $this->assertSame("\x00\x00\x01\x00", substr($ico, 0, 4), 'ICO header');

        $svg = file_get_contents(public_path('favicon.svg'));
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringNotContainsString('<script', $svg);
    }

    public function test_public_and_admin_pages_link_both_icons(): void
    {
        foreach (['/', '/contact', '/admin/login', '/definitely-missing'] as $path) {
            $html = $this->get($path)->getContent();

            $this->assertMatchesRegularExpression('#<link rel="icon" href="[^"]*favicon\.svg\?v=[0-9a-z]+" type="image/svg\+xml">#', $html, $path);
            $this->assertMatchesRegularExpression('#<link rel="icon" href="[^"]*favicon\.ico\?v=[0-9a-z]+" sizes="32x32">#', $html, $path);
        }
    }
}
