<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Writes audit_logs rows (FR-ADM-12, NFR-SEC-07) with the request's IP and
 * user agent. Values passed in must never include passwords or tokens.
 */
class AuditLogger
{
    public const LOGIN = 'login';

    public const LOGIN_FAILED = 'login_failed';

    public const LOGIN_THROTTLED = 'login_throttled';

    public const LOGOUT = 'logout';

    public const PASSWORD_CHANGED = 'password_changed';

    public const PASSWORD_RESET_REQUESTED = 'password_reset_requested';

    public const PASSWORD_RESET = 'password_reset';

    public function __construct(private Request $request) {}

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(string $action, ?User $actor = null, ?Model $entity = null, ?array $old = null, ?array $new = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'entity_type' => $entity?->getMorphClass(),
            'entity_id' => $entity?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
