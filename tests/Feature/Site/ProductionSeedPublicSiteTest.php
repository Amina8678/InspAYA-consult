<?php

namespace Tests\Feature\Site;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What a visitor sees on a freshly deployed production site, before the
 * client has supplied any copy: no placeholder text, no example.com address
 * and no fake phone number anywhere in the HTML, JSON-LD or sitemap.
 */
class ProductionSeedPublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['inspaya.super_admin' => [
            'name' => 'Test Admin', 'username' => 'test-admin',
            'email' => 'admin@example.test', 'password' => 'a-long-enough-password-1',
        ]]);
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publicPaths(): array
    {
        $paths = ['/', '/services', '/core-values', '/contact', '/consultants', '/insights', '/sitemap.xml'];

        return array_combine($paths, array_map(fn (string $path) => [$path], $paths));
    }

    #[DataProvider('publicPaths')]
    public function test_no_placeholder_or_fake_contact_detail_is_public(string $path): void
    {
        $html = $this->get('https://localhost'.$path)->assertOk()->getContent();

        $this->assertStringNotContainsString('PLACEHOLDER', $html);
        $this->assertStringNotContainsString('example.com', $html);
        $this->assertStringNotContainsString('555 0100', $html);
        $this->assertStringNotContainsString('mailto:', $html);
        $this->assertStringNotContainsString('tel:', $html);
        $this->assertDoesNotMatchRegularExpression('/<meta name="description" content=""/', $html);
    }

    public function test_structured_data_omits_the_blank_contact_fields(): void
    {
        $html = $this->get('https://localhost/')->assertOk()->getContent();

        preg_match('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $html, $m);
        $data = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Organization', $data['@type']);
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('telephone', $data);
    }

    public function test_inactive_services_are_neither_listed_nor_reachable_nor_in_the_sitemap(): void
    {
        $this->get('https://localhost/services/corporate-legal-consultations')->assertNotFound();
        $this->get('https://localhost/sitemap.xml')->assertDontSee('/services/', false);
    }
}
