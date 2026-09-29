<?php

namespace Tests\Feature\Site;

use Illuminate\Support\Facades\Route;

/**
 * Custom 500 page (item 1): same style as 404, noindex, and — regardless of
 * APP_DEBUG — never a stack trace or exception message. Laravel itself only
 * consults resources/views/errors/500.blade.php when app.debug is false (a
 * genuine unhandled exception with debug on shows Ignition instead, by
 * framework design); this view's own content is hardcoded and generic
 * either way, so it is safe even if that ever changes.
 */
class Error500Test extends SiteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->prefix('__test')->group(function () {
            Route::get('/throw', function () {
                throw new \RuntimeException('Secret internal detail that must never reach a visitor.');
            });
        });
    }

    public function test_custom_500_page_is_used_and_not_indexed(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/__test/throw')->assertStatus(500);

        $response->assertSee('Something went wrong')
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertDontSee('rel="canonical"', false)
            ->assertSee('<nav class="main-nav" aria-label="Main">', false)
            ->assertSee('class="site-footer"', false)
            ->assertSee(route('contact'), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_500_page_never_leaks_the_exception_message_or_a_stack_trace(): void
    {
        config(['app.debug' => false]);

        $html = $this->get('/__test/throw')->getContent();

        $this->assertStringNotContainsString('Secret internal detail', $html);
        $this->assertStringNotContainsString('RuntimeException', $html);
        $this->assertStringNotContainsString(__FILE__, $html);
        $this->assertStringNotContainsString('Stack trace', $html);
    }

    public function test_view_source_never_interpolates_exception_data(): void
    {
        $source = file_get_contents(resource_path('views/errors/500.blade.php'));

        foreach (['$exception', '$e->getMessage', 'getTrace', '{!!'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source, $forbidden);
        }
    }
}
