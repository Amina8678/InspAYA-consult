<?php

namespace App\Models\Concerns;

use App\Models\PendingRevision;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * pending_revisions is polymorphic, so the database cannot cascade: deleting
 * the record deletes its revisions here instead. Callers delete inside a
 * transaction so the two go together.
 */
trait HasPendingRevisions
{
    protected static function bootHasPendingRevisions(): void
    {
        static::deleting(fn (self $model) => $model->pendingRevisions()->delete());
    }

    /**
     * @return MorphMany<PendingRevision, $this>
     */
    public function pendingRevisions(): MorphMany
    {
        return $this->morphMany(PendingRevision::class, 'revisable');
    }
}
