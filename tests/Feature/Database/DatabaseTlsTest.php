<?php

namespace Tests\Feature\Database;

use App\Support\DatabaseTls;
use PHPUnit\Framework\TestCase;

class DatabaseTlsTest extends TestCase
{
    /** @return array<string, mixed> */
    private function pgsql(array $overrides = []): array
    {
        return array_merge(['driver' => 'pgsql', 'host' => 'ep-x.eu-central-1.aws.neon.tech', 'sslmode' => 'require'], $overrides);
    }

    public function test_remote_postgres_with_an_encrypting_sslmode_passes(): void
    {
        foreach (['require', 'verify-ca', 'verify-full'] as $mode) {
            $this->assertNull(DatabaseTls::problem($this->pgsql(['sslmode' => $mode])), $mode);
        }
    }

    public function test_remote_postgres_that_can_fall_back_to_plaintext_fails(): void
    {
        foreach (['disable', 'allow', 'prefer'] as $mode) {
            $this->assertStringContainsString('DB_SSLMODE', (string) DatabaseTls::problem($this->pgsql(['sslmode' => $mode])), $mode);
        }

        $this->assertNotNull(DatabaseTls::problem($this->pgsql(['sslmode' => null])), 'unset defaults to prefer');
    }

    public function test_sslmode_inside_a_database_url_counts(): void
    {
        $this->assertNull(DatabaseTls::problem(['driver' => 'pgsql', 'url' => 'postgresql://u:p@ep-x.neon.tech/db?sslmode=require']));
        $this->assertNotNull(DatabaseTls::problem(['driver' => 'pgsql', 'url' => 'postgresql://u:p@ep-x.neon.tech/db']));
    }

    public function test_a_local_database_and_sqlite_are_exempt(): void
    {
        $this->assertNull(DatabaseTls::problem($this->pgsql(['host' => '127.0.0.1', 'sslmode' => 'prefer'])));
        $this->assertNull(DatabaseTls::problem($this->pgsql(['host' => '/var/run/postgresql', 'sslmode' => 'prefer'])));
        $this->assertNull(DatabaseTls::problem(['driver' => 'sqlite', 'database' => ':memory:']));
    }

    public function test_remote_mysql_needs_tls_settings(): void
    {
        $this->assertNotNull(DatabaseTls::problem(['driver' => 'mysql', 'host' => 'db.internal.test', 'options' => []]));
        $this->assertNull(DatabaseTls::problem(['driver' => 'mysql', 'host' => 'db.internal.test', 'options' => [1009 => '/etc/ssl/ca.pem']]));
    }
}
