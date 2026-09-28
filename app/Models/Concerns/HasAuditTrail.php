<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Inverse of AuditLog::auditable(): the history of changes to this record.
 */
trait HasAuditTrail
{
    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditTrail(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable', 'entity_type', 'entity_id');
    }
}
