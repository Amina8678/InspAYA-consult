<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Health checks for uptime monitors and on-call staff.
 *
 * `/up` is public and reveals nothing beyond up or down: it runs one trivial
 * query, so it fails when the database does (the framework's default `/up`
 * page only proved PHP was running). It sits outside the session and cookie
 * middleware, so a monitor polling it every few seconds does not create a
 * session row per probe.
 *
 * `/admin/health` adds the operational detail (queue backlog, failed jobs,
 * scheduler heartbeat) and is only reachable by people who can manage site
 * settings.
 */
class HealthController extends Controller
{
    /** Cache key the scheduler refreshes every minute (routes/console.php). */
    public const SCHEDULER_HEARTBEAT = 'scheduler.heartbeat';

    public function up(): JsonResponse
    {
        $ok = $this->databaseIsUp();

        return response()->json(['status' => $ok ? 'ok' : 'unavailable'], $ok ? 200 : 503)
            ->withHeaders(['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
    }

    public function details(): JsonResponse
    {
        $database = $this->databaseIsUp();

        $detail = ['database' => $database ? 'ok' : 'down'];

        if ($database) {
            $oldest = Schema::hasTable('jobs') ? DB::table('jobs')->min('created_at') : null;
            $heartbeat = Cache::get(self::SCHEDULER_HEARTBEAT);

            $detail += [
                'queue_pending' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
                'queue_oldest_pending_seconds' => $oldest === null ? null : max(0, now()->timestamp - (int) $oldest),
                'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
                'scheduler_last_run_seconds' => $heartbeat === null ? null : max(0, now()->timestamp - (int) $heartbeat),
            ];
        }

        return response()->json($detail, $database ? 200 : 503)->withHeaders(['Cache-Control' => 'no-store']);
    }

    private function databaseIsUp(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
