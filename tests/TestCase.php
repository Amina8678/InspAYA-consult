<?php

namespace Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to boot against anything but SQLite or a database whose name ends
     * in "_testing". RefreshDatabase wipes the target, so a mis-set DB_DATABASE
     * must never reach a development database such as `inspaya`.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        static::guardAgainstNonTestingDatabase($app['config']);

        return $app;
    }

    /**
     * @param  Repository  $configRepository
     */
    public static function guardAgainstNonTestingDatabase($configRepository): void
    {
        $connection = $configRepository->get('database.default');
        $config = $configRepository->get("database.connections.{$connection}", []);

        if (($config['driver'] ?? null) !== 'sqlite') {
            $database = (string) ($config['database'] ?? '');

            if (! empty($config['url'])) {
                $parsedPath = parse_url((string) $config['url'], PHP_URL_PATH);
                $database = is_string($parsedPath) ? ltrim($parsedPath, '/') : '';
            }

            if (! str_ends_with($database, '_testing')) {
                throw new RuntimeException(
                    "Refusing to run tests against [{$connection}] database [{$database}]: "
                    .'use SQLite or a database whose name ends in "_testing".'
                );
            }
        }
    }
}
