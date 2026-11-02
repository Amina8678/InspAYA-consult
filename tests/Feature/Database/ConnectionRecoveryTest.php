<?php

namespace Tests\Feature\Database;

use Illuminate\Database\LostConnectionDetector;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use ReflectionMethod;
use Tests\TestCase;

class ConnectionRecoveryTest extends TestCase
{
    public function test_a_disconnected_database_connection_automatically_reconnects_on_next_query(): void
    {
        $connection = DB::connection();
        $this->assertNotNull($connection->getPdo());

        DB::disconnect();
        $this->assertNull($connection->getPdo());

        $result = DB::select('SELECT 1 as result');
        $this->assertNotEmpty($result);
        $this->assertNotNull($connection->getPdo());
    }

    public function test_a_dropped_connection_with_neon_idle_suspend_error_reconnects_and_retries(): void
    {
        $connection = DB::connection();
        $connection->getPdo();

        $detector = new LostConnectionDetector;

        // Neon idle compute suspend and connection drop errors
        $this->assertTrue($detector->causedByLostConnection(new PDOException('server closed the connection unexpectedly')));
        $this->assertTrue($detector->causedByLostConnection(new PDOException('SSL connection has been closed unexpectedly')));
        $this->assertTrue($detector->causedByLostConnection(new PDOException('SSL SYSCALL error: EOF detected')));

        // Verify tryAgainIfCausedByLostConnection re-runs callback upon lost connection
        $method = new ReflectionMethod($connection, 'tryAgainIfCausedByLostConnection');
        $exception = new QueryException(
            $connection->getName(),
            'select 1',
            [],
            new PDOException('server closed the connection unexpectedly')
        );

        $attempts = 0;
        $retried = $method->invoke(
            $connection,
            $exception,
            'select 1',
            [],
            function () use (&$attempts) {
                $attempts++;

                return 'recovered';
            }
        );

        $this->assertSame('recovered', $retried);
        $this->assertSame(1, $attempts);
    }

    /**
     * What Neon's scale-to-zero looks like to the app: the server side of an
     * open connection disappears, and the next request must still succeed.
     * Here the backend is killed from a second connection on a real engine.
     */
    public function test_the_first_query_after_the_server_kills_the_connection_succeeds(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            $this->markTestSkipped('SQLite has no server process to kill.');
        }

        $before = $driver === 'pgsql'
            ? $connection->selectOne('select pg_backend_pid() as id')->id
            : $connection->selectOne('select connection_id() as id')->id;

        config(['database.connections.killer' => config('database.connections.'.$connection->getName())]);
        $killer = DB::connection('killer');
        $driver === 'pgsql'
            ? $killer->select('select pg_terminate_backend(?)', [$before])
            : $killer->statement('KILL '.(int) $before);
        DB::purge('killer');
        usleep(300_000);

        // No exception reaches the caller; Laravel reconnects and retries.
        $after = $driver === 'pgsql'
            ? $connection->selectOne('select pg_backend_pid() as id')->id
            : $connection->selectOne('select connection_id() as id')->id;

        $this->assertNotEquals($before, $after, 'a new server connection was opened');
    }
}
