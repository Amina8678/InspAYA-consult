<?php

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Public site tests. The real Blade views are owned by the frontend and may
 * not exist yet, so minimal stubs from tests/Fixtures/views are put first on
 * the view path. Nothing is added to resources/views/.
 */
abstract class SiteTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        View::getFinder()->prependLocation(base_path('tests/Fixtures/views'));
    }

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
