<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\PendingRevision;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Real concurrency, on real MySQL connections: two first-ever submissions for
 * the same page must produce one pending revision, not two.
 *
 * Why this needs its own harness: RefreshDatabase wraps a test in a
 * transaction that other connections cannot see, so this uses
 * DatabaseMigrations (committed data) and runs each submission in a separate
 * PHP process. The test holds the live page's row lock itself; a submission
 * that locks the page row (as it must, because on a first submission there is
 * no pending row to lock) blocks until the lock is released.
 */
class PendingRevisionConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Row locking is MySQL-specific; this test needs two real MySQL connections.');
        }

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function submitInBackground(Page $page, User $user, string $title): Process
    {
        $c = config('database.connections.mysql');
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
            'DB_HOST' => (string) $c['host'], 'DB_PORT' => (string) $c['port'], 'DB_DATABASE' => (string) $c['database'],
            'DB_USERNAME' => (string) $c['username'], 'DB_PASSWORD' => (string) $c['password'],
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ];

        $process = new Process([PHP_BINARY, __DIR__.'/Support/submit_pending_revision.php', base_path(), (string) $page->id, (string) $user->id, $title], base_path(), $env);
        $process->setTimeout(30);
        $process->start();

        return $process;
    }

    /** A second connection that locks the live page row, as a competing request would. */
    private function holdPageLock(Page $page): \PDO
    {
        $c = config('database.connections.mysql');
        $pdo = new \PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['database']}", $c['username'], $c['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->beginTransaction();
        $pdo->query('select id from pages where id = '.(int) $page->id.' for update');

        return $pdo;
    }

    private function waitUntilBlocked(Process ...$processes): void
    {
        // Give each process time to boot and reach the lock; a submission
        // that does not lock the page would have finished by now.
        $deadline = microtime(true) + 6;
        while (microtime(true) < $deadline) {
            usleep(200_000);
            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    $this->fail('A submission finished while the page row was locked: it does not lock the live record. Output: '.$process->getOutput().$process->getErrorOutput());
                }
            }
        }
    }

    public function test_a_first_submission_waits_for_the_live_page_row_lock(): void
    {
        $page = Page::factory()->published()->create();
        $editor = User::factory()->withRole('editor')->create();

        $lock = $this->holdPageLock($page);
        $process = $this->submitInBackground($page, $editor, 'Waiting');

        $this->waitUntilBlocked($process);
        $this->assertSame(0, PendingRevision::count(), 'Nothing is written while the page row is locked.');

        $lock->commit();
        $process->wait();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame('Waiting', PendingRevision::sole()->changes['title']);
    }

    public function test_two_simultaneous_first_submissions_leave_exactly_one_pending_revision(): void
    {
        $page = Page::factory()->published()->create();
        $editorA = User::factory()->withRole('editor')->create();
        $editorB = User::factory()->withRole('editor')->create();

        // Both start, and both queue behind the page lock; only then do they
        // run, one after the other.
        $lock = $this->holdPageLock($page);
        $a = $this->submitInBackground($page, $editorA, 'From A');
        $b = $this->submitInBackground($page, $editorB, 'From B');
        $this->waitUntilBlocked($a, $b);

        $lock->commit();
        $a->wait();
        $b->wait();

        $this->assertSame(0, $a->getExitCode(), $a->getErrorOutput());
        $this->assertSame(0, $b->getExitCode(), $b->getErrorOutput());

        $this->assertSame(1, PendingRevision::count(), 'Two first submissions must not both insert.');
        $this->assertContains(PendingRevision::sole()->changes['title'], ['From A', 'From B'], 'The later submission replaced the earlier one.');
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'revision_submitted')->count());
    }
}
