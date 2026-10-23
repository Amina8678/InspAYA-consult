<?php

namespace App\Support;

use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * The form being submitted was loaded from an older version of the page or
 * post than the one now stored: someone else saved in between.
 */
class StaleEdit extends RuntimeException
{
    /**
     * Throws unless $submitted (the version carried in the form's hidden
     * field) matches the stored version of $locked. Call it inside the
     * transaction that holds the row lock and then writes, so no save can
     * land between the check and the write. A form without a version (an old
     * page left open across a deploy) is not checked.
     */
    public static function assertCurrent(Model $locked, mixed $submitted): void
    {
        if ($submitted === null || $submitted === '' || (int) $submitted === (int) $locked->version) {
            return;
        }

        throw new self('This '.($locked instanceof BlogPost ? 'post' : 'page').' was changed by someone else since you opened it, so your changes were not saved. '
            .'What you typed is still in the form below. Open the current version to see what changed, then make your edits again.');
    }
}
