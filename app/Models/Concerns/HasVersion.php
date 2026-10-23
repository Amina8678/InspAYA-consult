<?php

namespace App\Models\Concerns;

use Illuminate\Database\Query\Expression;

/**
 * An integer `version`, incremented on every update to the row.
 *
 * Done in the model's `updating` event, as an SQL expression (`version + 1`),
 * so it is atomic and covers every Eloquent save, `update()` and `touch()`
 * on a model instance. Writes that bypass model events (query-builder
 * updates, or relation-only changes such as a tag sync that leaves the row
 * itself untouched) must call bumpVersion() or `incrementVersionWhere()`
 * themselves; the places that do are listed in docs/content-approval.md and
 * covered by VersionedRecordsTest.
 */
trait HasVersion
{
    protected static function bootHasVersion(): void
    {
        static::creating(function (self $model) {
            $model->version ??= 1;
        });

        static::updating(function (self $model) {
            // Only a save that actually writes something counts as a change.
            if ($model->isDirty() && ! $model->isDirty('version')) {
                $model->setAttribute('version', new Expression('version + 1'));
            }
        });

        static::updated(function (self $model) {
            if ($model->getAttributes()['version'] instanceof Expression) {
                // Read back what the database now holds, so the instance
                // never carries the expression (or a stale number) around.
                $model->setAttribute('version', (int) $model->newQuery()->whereKey($model->getKey())->value('version'));
            }
        });
    }

    /**
     * Count a change that did not go through save(), for example a pivot
     * (tags) sync. Atomic, and visible on this instance afterwards.
     */
    public function bumpVersion(): void
    {
        $this->newQuery()->whereKey($this->getKey())->toBase()->increment('version');

        $this->setAttribute('version', (int) $this->newQuery()->whereKey($this->getKey())->value('version'));
        $this->syncOriginalAttribute('version');
    }
}
