<?php

namespace Tests\Feature\Database;

use Illuminate\Config\Repository;
use RuntimeException;
use Tests\TestCase;

class TestCaseSafetyGuardTest extends TestCase
{
    public function test_it_allows_testing_databases_and_sqlite(): void
    {
        $repo = new Repository([
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
                ],
            ],
        ]);

        TestCase::guardAgainstNonTestingDatabase($repo);
        $this->assertTrue(true);
    }

    public function test_it_refuses_non_testing_databases_on_postgres_and_mysql(): void
    {
        $repo = new Repository([
            'database' => [
                'default' => 'pgsql',
                'connections' => [
                    'pgsql' => [
                        'driver' => 'pgsql',
                        'database' => 'inspaya_production',
                    ],
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run tests against [pgsql] database [inspaya_production]');
        TestCase::guardAgainstNonTestingDatabase($repo);
    }

    public function test_it_refuses_non_testing_database_urls(): void
    {
        $repo = new Repository([
            'database' => [
                'default' => 'pgsql',
                'connections' => [
                    'pgsql' => [
                        'driver' => 'pgsql',
                        'url' => 'postgres://user:pass@ep-xyz.neon.tech/inspaya_live',
                    ],
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run tests against [pgsql] database [inspaya_live]');
        TestCase::guardAgainstNonTestingDatabase($repo);
    }

    public function test_it_accepts_testing_database_urls(): void
    {
        $repo = new Repository([
            'database' => [
                'default' => 'pgsql',
                'connections' => [
                    'pgsql' => [
                        'driver' => 'pgsql',
                        'url' => 'postgres://user:pass@ep-xyz.neon.tech/inspaya_testing',
                    ],
                ],
            ],
        ]);

        TestCase::guardAgainstNonTestingDatabase($repo);
        $this->assertTrue(true);
    }
}
