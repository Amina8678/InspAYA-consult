<?php

/*
 * Subprocess helper for TrustedProxiesEnvFileTest: boots a fresh copy of the
 * application reading its .env from a given directory, then either caches the
 * config or serves one request that arrives from a proxy claiming HTTPS.
 *
 * Usage: php boot_with_env_file.php <base> <envDir> <cache|request> [remoteAddr]
 */

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

[, $base, $envDir, $mode, $remoteAddr] = $argv + [4 => '127.0.0.1'];

require $base.'/vendor/autoload.php';

$app = require $base.'/bootstrap/app.php';
$app->useEnvironmentPath($envDir);

if ($mode === 'cache') {
    // Equivalent of `config:cache`, done by hand: the real command boots a
    // second, fresh application from the project's own .env, which this
    // helper must never read.
    $app->make(ConsoleKernel::class)->bootstrap();
    file_put_contents($app->getCachedConfigPath(), '<?php return '.var_export($app['config']->all(), true).';'.PHP_EOL);

    return;
}

$request = Request::create('http://example.test/robots.txt', 'GET', server: [
    'REMOTE_ADDR' => $remoteAddr,
    'HTTP_X_FORWARDED_PROTO' => 'https',
]);

$response = $app->make(HttpKernel::class)->handle($request);

echo json_encode([
    'status' => $response->getStatusCode(),
    'location' => $response->headers->get('Location'),
    'hsts' => $response->headers->get('Strict-Transport-Security'),
]);
