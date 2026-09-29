<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLogPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Audit log viewer (NFR-SEC-07, plan §6 row 32: Super Admin and
 * Administrator only). Read-only: there is no create, edit or delete route,
 * matching AuditLog's model-level immutability (D9).
 */
class AuditLogController extends Controller
{
    public const PER_PAGE = 30;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $entityType = array_key_exists((string) $request->query('entity_type'), AuditLogPresenter::ENTITY_TYPES)
            ? $request->query('entity_type') : null;
        $action = in_array($request->query('action'), AuditLogPresenter::ACTIONS, true) ? $request->query('action') : null;
        $actor = $request->query('actor');
        $from = $this->parseDate($request->query('from'));
        $to = $this->parseDate($request->query('to'));

        $logs = AuditLog::query()
            ->select(['id', 'user_id', 'action', 'entity_type', 'entity_id', 'created_at'])
            ->with(['user:id,name,role_id', 'user.role:id,name', 'auditable'])
            ->when($entityType !== null, fn ($q) => $q->where('entity_type', $entityType))
            ->when($action !== null, fn ($q) => $q->where('action', $action))
            ->when($actor === 'guest', fn ($q) => $q->whereNull('user_id'))
            ->when($actor !== null && $actor !== 'guest' && ctype_digit((string) $actor), fn ($q) => $q->where('user_id', (int) $actor))
            ->when($from !== null, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'filters' => compact('entityType', 'action', 'actor', 'from', 'to'),
            'actors' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $this->authorize('view', $auditLog);

        return view('admin.audit-logs.show', [
            'log' => $auditLog->load(['user:id,name,role_id', 'user.role:id,name', 'auditable']),
        ]);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)?->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
