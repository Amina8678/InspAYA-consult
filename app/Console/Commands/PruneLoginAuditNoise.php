<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes old login_failed / login_throttled audit rows, the only audit
 * actions an anonymous visitor can generate. Everything else in audit_logs
 * is kept.
 *
 * AuditLog is immutable through Eloquent (its updating/deleting model events
 * throw). Those events only fire for model instances, so this deletes with
 * the query builder, which never boots the model. The action list below is a
 * hard-coded constant: no option or argument can widen it.
 */
class PruneLoginAuditNoise extends Command
{
    /** The only actions this command may ever delete. */
    public const ACTIONS = ['login_failed', 'login_throttled'];

    private const CHUNK = 1000;

    protected $signature = 'audit:prune-login-noise {--days= : Delete rows older than this many days (default: config inspaya.audit_login_retention_days)}';

    protected $description = 'Delete login_failed and login_throttled audit rows older than the retention period';

    public function handle(): int
    {
        $days = $this->option('days') ?? config('inspaya.audit_login_retention_days');

        if (! is_numeric($days) || (int) $days != $days || (int) $days < 1) {
            $this->error('The number of days must be a whole number of at least 1.');

            return self::INVALID;
        }

        $cutoff = now()->subDays((int) $days);
        $deleted = 0;

        do {
            $ids = DB::table('audit_logs')
                ->whereIn('action', self::ACTIONS)
                ->where('created_at', '<', $cutoff)
                ->limit(self::CHUNK)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                $deleted += DB::table('audit_logs')
                    ->whereIn('id', $ids)
                    ->whereIn('action', self::ACTIONS)
                    ->delete();
            }
        } while ($ids->count() === self::CHUNK);

        $this->info("Deleted {$deleted} login audit ".str('row')->plural($deleted)." older than {$days} days.");

        return self::SUCCESS;
    }
}
