<?php

namespace App\Services\Ordering;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Race-safe sort_order handling for services, consultants and core values
 * (fixes finding F2 in the schema plan: unprotected "max + 1").
 *
 * Both operations lock the table's rows (SELECT … FOR UPDATE) inside a
 * transaction, so two editors saving at the same moment are serialised
 * instead of reading the same maximum or swapping against stale positions.
 */
class SortOrder
{
    /**
     * Save a new record at the end of the list. Call instead of save().
     */
    public function append(Model $model): void
    {
        DB::transaction(function () use ($model) {
            $max = $model->newQuery()->lockForUpdate()->max('sort_order');
            $model->sort_order = ((int) $max) + 1;
            $model->save();
        });
    }

    /**
     * Move one step up or down. Positions are renumbered 1..n under the lock,
     * so gaps and duplicates left by older data are repaired as a side effect.
     *
     * @return array{from: int, to: int} the record's old and new position
     */
    public function move(Model $model, string $direction): array
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException("Unknown direction [{$direction}].");
        }

        return DB::transaction(function () use ($model, $direction) {
            $ids = $model->newQuery()
                ->orderBy('sort_order')
                ->orderBy($model->getKeyName())
                ->lockForUpdate()
                ->pluck($model->getKeyName())
                ->all();

            // Loose match: drivers return ids as int or string.
            $from = array_search($model->getKey(), $ids);
            if ($from === false) {
                throw new InvalidArgumentException('The record is not in its own table.');
            }
            $to = $direction === 'up' ? $from - 1 : $from + 1;

            if (isset($ids[$to])) {
                [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
            } else {
                $to = $from;
            }

            foreach ($ids as $index => $id) {
                $model->newQuery()->whereKey($id)->where('sort_order', '!=', $index + 1)->update(['sort_order' => $index + 1]);
            }

            $model->sort_order = $to + 1;

            return ['from' => (int) $from + 1, 'to' => (int) $to + 1];
        });
    }
}
