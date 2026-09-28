<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

/**
 * §7 "Manage media library" (Full / Full / Full / Limited), plan §6 rows
 * 23–26. Authors' "Limited" = edit/replace/delete only their own uploads.
 */
class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('media.view');
    }

    public function view(User $user, Media $media): bool
    {
        return $user->hasPermission('media.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('media.upload');
    }

    public function update(User $user, Media $media): bool
    {
        return $this->canModify($user, $media);
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->canModify($user, $media);
    }

    private function canModify(User $user, Media $media): bool
    {
        return $user->hasPermission('media.manage')
            || ($user->hasPermission('media.manage-own') && $media->uploader_id === $user->id);
    }
}
