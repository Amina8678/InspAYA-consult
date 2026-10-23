<?php

namespace App\Support;

use RuntimeException;

/**
 * A pending revision cannot be applied as it stands. The message is written
 * for the person approving it and is shown to them as-is.
 */
class RevisionConflict extends RuntimeException
{
    /** The live record was changed after the revision was submitted. */
    public const LIVE_RECORD_CHANGED = 'live_record_changed';

    /** The revision's URL slug now belongs to another record. */
    public const SLUG_TAKEN = 'slug_taken';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function liveRecordChanged(): self
    {
        return new self(self::LIVE_RECORD_CHANGED,
            'This page or post was changed after the edit was submitted. Check the differences below: approving overwrites the fields shown with the proposed values. Tick the confirmation to approve anyway, or reject it and ask for a fresh edit.');
    }

    public static function slugTaken(string $slug): self
    {
        return new self(self::SLUG_TAKEN,
            'The URL "'.$slug.'" is now used by another page or post, so this change cannot be approved as it is. Reject it and ask for a different URL.');
    }
}
