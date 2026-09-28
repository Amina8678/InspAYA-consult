<?php

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Public site tests, rendered through the real Blade views in
 * resources/views/public.
 */
abstract class SiteTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Every public page carries the full SEO block.
     */
    protected function assertSeo(TestResponse $response): void
    {
        $response->assertViewHas('seo', function (array $seo) {
            $this->assertSame(['title', 'description', 'canonical_url', 'og_image'], array_keys($seo));
            $this->assertNotEmpty($seo['title']);
            $this->assertNotEmpty($seo['canonical_url']);

            return true;
        });
    }
}
