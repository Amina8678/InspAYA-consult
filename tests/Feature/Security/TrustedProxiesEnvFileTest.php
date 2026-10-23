<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * TRUSTED_PROXIES must work when it appears ONLY in a .env file, with and
 * without a cached config. Each case boots a fresh application in a
 * subprocess with a scrubbed process environment, so nothing leaks in from
 * PHPUnit's own environment (an earlier version of this setting read env()
 * in bootstrap/app.php, which runs before .env is loaded, and was silently
 * ignored). Deliberately a plain TestCase: it never boots this process's app.
 */
class TrustedProxiesEnvFileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/trusted-proxies-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/{,.}*', GLOB_BRACE) ?: [] as $file) {
            is_file($file) && unlink($file);
        }
        rmdir($this->dir);

        parent::tearDown();
    }

    public function test_a_proxy_listed_only_in_dot_env_is_trusted_without_a_redirect_loop(): void
    {
        $this->writeEnv('TRUSTED_PROXIES=10.0.0.5');

        $response = $this->request(remoteAddr: '10.0.0.5');

        $this->assertSame(200, $response['status']);
        $this->assertNull($response['location']);
        $this->assertNotNull($response['hsts'], 'HTTPS was detected, so HSTS is sent.');
    }

    public function test_a_proxy_not_listed_is_ignored_and_the_request_is_redirected_to_https(): void
    {
        $this->writeEnv('TRUSTED_PROXIES=10.0.0.5');

        $response = $this->request(remoteAddr: '10.0.0.9');

        $this->assertSame(301, $response['status']);
        $this->assertStringStartsWith('https://', $response['location']);
    }

    public function test_a_blank_value_trusts_nobody(): void
    {
        $this->writeEnv('TRUSTED_PROXIES=');

        $this->assertSame(301, $this->request(remoteAddr: '10.0.0.5')['status']);
    }

    public function test_the_value_survives_config_cache_with_the_env_file_gone(): void
    {
        $this->writeEnv('TRUSTED_PROXIES=10.0.0.5');
        $this->run_('cache');
        unlink($this->dir.'/.env');

        $this->assertSame(200, $this->request(remoteAddr: '10.0.0.5')['status']);
        $this->assertSame(301, $this->request(remoteAddr: '10.0.0.9')['status']);
    }

    private function writeEnv(string $proxies): void
    {
        file_put_contents($this->dir.'/.env', implode("\n", [
            'APP_ENV=production',
            'APP_DEBUG=false',
            'APP_KEY=base64:'.base64_encode(str_repeat('k', 32)),
            'APP_URL=https://example.test',
            'DB_CONNECTION=sqlite',
            'DB_DATABASE=:memory:',
            'SESSION_DRIVER=array',
            'CACHE_STORE=array',
            'QUEUE_CONNECTION=sync',
            $proxies,
        ])."\n");
    }

    /**
     * @return array{status: int, location: ?string, hsts: ?string}
     */
    private function request(string $remoteAddr): array
    {
        return json_decode($this->run_('request', $remoteAddr), true, flags: JSON_THROW_ON_ERROR);
    }

    private function run_(string $mode, string $remoteAddr = '127.0.0.1'): string
    {
        $base = dirname(__DIR__, 3);

        // Scrub the inherited environment: only what a real server process
        // would have, plus where this run's config cache lives.
        $env = array_fill_keys(array_keys(getenv()), false);
        $env['PATH'] = getenv('PATH');
        $env['APP_CONFIG_CACHE'] = $this->dir.'/config.php';

        $process = new Process([PHP_BINARY, __DIR__.'/Support/boot_with_env_file.php', $base, $this->dir, $mode, $remoteAddr], $base, $env);
        $process->mustRun();

        return $process->getOutput();
    }
}
