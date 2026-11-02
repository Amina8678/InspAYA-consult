<?php

namespace Tests\Feature\Deploy;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

/**
 * render.yaml and the container scripts are only exercised on Render, so pin
 * the promises docs/render.md makes about them. Plain text checks: no YAML
 * parser is installed and render.yaml is deliberately flat.
 */
class RenderBlueprintTest extends TestCase
{
    private string $root;

    private string $yaml;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 3);
        $this->yaml = file_get_contents($this->root.'/render.yaml');
    }

    /** The text between a top-level `key:` and the next top-level key. */
    private function section(string $key): string
    {
        $this->assertSame(1, preg_match('/^'.$key.':\n(.*?)(?=^\S|\z)/ms', $this->yaml, $m), "no {$key} section");

        return $m[1];
    }

    /** @return array<string, string> service type => its text */
    private function services(): array
    {
        $parts = preg_split('/^  - type: /m', $this->section('services'), -1, PREG_SPLIT_NO_EMPTY);
        $services = [];
        foreach ($parts as $part) {
            $services[trim(strtok($part, "\n"))] = $part;
        }

        return $services;
    }

    public function test_it_declares_only_a_web_and_a_worker_service_and_no_database_or_cron(): void
    {
        $this->assertSame(['web', 'worker'], array_keys($this->services()));
        $this->assertDoesNotMatchRegularExpression('/^databases:|type: (cron|pserv|redis|keyvalue)/m', $this->yaml);
    }

    public function test_web_is_health_checked_migrates_before_deploy_and_never_seeds(): void
    {
        $web = $this->services()['web'];

        $this->assertStringContainsString('runtime: docker', $web);
        $this->assertStringContainsString('healthCheckPath: /up', $web);
        $this->assertStringContainsString('preDeployCommand: php artisan migrate --force', $web);
        $this->assertStringContainsString('mountPath: /var/www/html/storage/app/public', $web);
        $this->assertStringNotContainsString('db:seed', $this->yaml);
        $this->assertStringNotContainsString('db:seed', file_get_contents($this->root.'/docker/entrypoint.sh'));
        $this->assertStringNotContainsString('migrate', file_get_contents($this->root.'/docker/entrypoint.sh'));
    }

    public function test_the_worker_has_no_disk_and_both_services_use_the_shared_group(): void
    {
        $services = $this->services();

        $this->assertStringNotContainsString('disk:', $services['worker']);
        $this->assertStringContainsString('APP_ROLE', $services['worker']);
        foreach ($services as $service) {
            $this->assertStringContainsString('fromGroup: inspaya-shared', $service);
        }
    }

    public function test_the_group_holds_no_secrets_and_agrees_with_the_render_env_example(): void
    {
        $group = $this->section('envVarGroups');
        $this->assertStringNotContainsString('sync:', $group, 'Render rejects sync:false in a group.');

        preg_match_all('/- key: (\w+)\n\s+value: "?([^"\n]*)"?/', $group, $pairs, PREG_SET_ORDER);
        $this->assertNotEmpty($pairs);

        $example = Dotenv::parse(file_get_contents($this->root.'/.env.render.example'));
        foreach ($pairs as [, $key, $value]) {
            $this->assertNotContains($key, ['APP_KEY', 'DB_PASSWORD', 'MAIL_PASSWORD', 'SUPER_ADMIN_PASSWORD']);
            $this->assertArrayHasKey($key, $example, $key);
            if ($key !== 'APP_NAME') {
                $this->assertSame($example[$key], $value, $key);
            }
        }
        $this->assertContains('DB_SSLMODE', array_column($pairs, 1));
    }
}
