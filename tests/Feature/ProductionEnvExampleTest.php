<?php

namespace Tests\Feature;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

class ProductionEnvExampleTest extends TestCase
{
    /** @return array<string, ?string> */
    private function values(): array
    {
        return Dotenv::parse(file_get_contents(dirname(__DIR__, 2).'/.env.production.example'));
    }

    public function test_it_has_production_safe_defaults(): void
    {
        $env = $this->values();

        $this->assertSame('production', $env['APP_ENV']);
        $this->assertSame('false', $env['APP_DEBUG']);
        $this->assertSame('daily', $env['LOG_CHANNEL']);
        $this->assertSame('warning', $env['LOG_LEVEL']);
        $this->assertSame('mysql', $env['DB_CONNECTION']);
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
}
