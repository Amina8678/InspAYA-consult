<?php

namespace Tests;

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

        $connection = $app['config']->get('database.default');
        $config = $app['config']->get("database.connections.{$connection}", []);

        if (($config['driver'] ?? null) !== 'sqlite') {
            $database = (string) ($config['database'] ?? '');

            if (! empty($config['url']) || ! str_ends_with($database, '_testing')) {
                throw new RuntimeException(
                    "Refusing to run tests against [{$connection}] database [{$database}]: "
                    .'use SQLite or a database whose name ends in "_testing", with DB_URL empty.'
                );
            }
        }

        return $app;
    }
}
