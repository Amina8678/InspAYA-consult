<?php

namespace Tests\Feature;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

class ProductionEnvExampleTest extends TestCase
{
    /** @return array<string, ?string> */
    private function values(string $file = '.env.production.example'): array
    {
        return Dotenv::parse(file_get_contents(dirname(__DIR__, 2).'/'.$file));
    }

    /** Keys only the container scripts under docker/ read, not Laravel. */
    private const CONTAINER_KEYS = ['BASIC_AUTH_USER', 'BASIC_AUTH_PASSWORD', 'STAGING_NOINDEX', 'QUEUE_SLEEP', 'PHP_FPM_MAX_CHILDREN'];

    public function test_it_has_production_safe_defaults(): void
    {
        $env = $this->values();

        $this->assertSame('production', $env['APP_ENV']);
        $this->assertSame('false', $env['APP_DEBUG']);
        $this->assertSame('daily', $env['LOG_CHANNEL']);
        $this->assertSame('warning', $env['LOG_LEVEL']);
        $this->assertSame('pgsql', $env['DB_CONNECTION']);
        $this->assertSame('smtp', $env['MAIL_MAILER']);
        $this->assertSame('database', $env['QUEUE_CONNECTION']);
        $this->assertSame('true', $env['SESSION_SECURE_COOKIE']);
        $this->assertNotSame('Laravel', $env['APP_NAME']);
    }

    public function test_secrets_and_site_specific_values_are_left_blank(): void
    {
        $env = $this->values();

        foreach (['APP_KEY', 'APP_URL', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'TRUSTED_PROXIES', 'SUPER_ADMIN_PASSWORD'] as $key) {
            $this->assertSame('', $env[$key], $key.' must not ship with a value.');
        }
    }

    public function test_it_contains_no_example_domain_or_localhost(): void
    {
        $text = file_get_contents(dirname(__DIR__, 2).'/.env.production.example');

        $this->assertStringNotContainsString('example.com', $text);
        $this->assertStringNotContainsString('localhost', $text);
    }

    public function test_every_key_it_sets_is_one_the_app_reads(): void
    {
        $root = dirname(__DIR__, 2);
        $code = '';
        foreach (array_merge(glob($root.'/config/*.php'), [$root.'/bootstrap/app.php']) as $file) {
            $code .= file_get_contents($file);
        }

        // Framework-read keys that no config file in this repo spells out.
        $frameworkKeys = ['APP_KEY', 'APP_MAINTENANCE_DRIVER', 'BCRYPT_ROUNDS', 'BROADCAST_CONNECTION', 'LOG_DEPRECATIONS_CHANNEL', 'QUEUE_CONNECTION'];

        foreach (array_keys($this->values()) as $key) {
            $this->assertTrue(
                in_array($key, $frameworkKeys, true) || str_contains($code, "'{$key}'"),
                "{$key} is in .env.production.example but nothing reads it.",
            );
        }
    }

    // .env.render.example -------------------------------------------------

    public function test_the_render_example_uses_the_same_key_names_as_the_production_example(): void
    {
        $production = $this->values();
        $render = $this->values('.env.render.example');

        foreach (array_keys($render) as $key) {
            $this->assertTrue(
                array_key_exists($key, $production) || in_array($key, self::CONTAINER_KEYS, true) || $key === 'DB_SSLROOTCERT',
                "{$key} is in .env.render.example but not in .env.production.example.",
            );
        }

        // Nothing the app needs in production is forgotten on Render.
        $required = ['APP_KEY', 'APP_URL', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'MAIL_FROM_ADDRESS', 'SUPER_ADMIN_NAME', 'SUPER_ADMIN_USERNAME', 'SUPER_ADMIN_EMAIL', 'SUPER_ADMIN_PASSWORD', 'TRUSTED_PROXIES', 'QUEUE_CONNECTION', 'CACHE_STORE', 'SESSION_DRIVER'];
        $this->assertSame([], array_values(array_diff($required, array_keys($render))));
    }

    public function test_the_render_example_is_production_safe_and_set_up_for_render(): void
    {
        $env = $this->values('.env.render.example');

        $this->assertSame('production', $env['APP_ENV']);
        $this->assertSame('false', $env['APP_DEBUG']);
        $this->assertSame('stderr', $env['LOG_CHANNEL']);
        $this->assertSame('*', $env['TRUSTED_PROXIES']);
        $this->assertSame('true', $env['SESSION_SECURE_COOKIE']);
        $this->assertSame('pgsql', $env['DB_CONNECTION']);
        $this->assertContains($env['DB_SSLMODE'], ['require', 'verify-full']);
        foreach (['QUEUE_CONNECTION', 'CACHE_STORE', 'SESSION_DRIVER'] as $key) {
            $this->assertSame('database', $env[$key]);
        }
        $this->assertSame('smtp', $env['MAIL_MAILER']);
        $this->assertSame('smtp-relay.brevo.com', $env['MAIL_HOST']);
        $this->assertSame('587', $env['MAIL_PORT']);
        $this->assertSame('0', $env['STAGING_NOINDEX']);
        $this->assertNotSame('Laravel', $env['APP_NAME']);
    }

    public function test_the_render_example_ships_no_secrets_or_site_specific_values(): void
    {
        $env = $this->values('.env.render.example');

        foreach (['APP_KEY', 'APP_URL', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'SUPER_ADMIN_PASSWORD', 'BASIC_AUTH_USER', 'BASIC_AUTH_PASSWORD'] as $key) {
            $this->assertSame('', $env[$key], $key.' must not ship with a value.');
        }

        $text = file_get_contents(dirname(__DIR__, 2).'/.env.render.example');
        $this->assertStringNotContainsString('example.com', $text);
    }

    public function test_every_render_example_key_is_read_by_the_app_or_the_container_scripts(): void
    {
        $root = dirname(__DIR__, 2);
        $code = file_get_contents($root.'/bootstrap/app.php');
        foreach (glob($root.'/config/*.php') as $file) {
            $code .= file_get_contents($file);
        }
        $docker = file_get_contents($root.'/docker/entrypoint.sh').file_get_contents($root.'/docker/php/www.conf')
            .file_get_contents($root.'/docker/supervisor/supervisord-worker.conf');

        $frameworkKeys = ['APP_KEY', 'APP_MAINTENANCE_DRIVER', 'BCRYPT_ROUNDS', 'BROADCAST_CONNECTION', 'LOG_DEPRECATIONS_CHANNEL', 'QUEUE_CONNECTION'];

        foreach (array_keys($this->values('.env.render.example')) as $key) {
            if (in_array($key, self::CONTAINER_KEYS, true)) {
                $this->assertStringContainsString($key, $docker, "{$key} is documented but no docker/ script reads it.");

                continue;
            }
            $this->assertTrue(in_array($key, $frameworkKeys, true) || str_contains($code, "'{$key}'"), "{$key} is in .env.render.example but nothing reads it.");
        }
    }
}
