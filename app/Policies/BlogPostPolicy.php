<?php

namespace App\Policies;

use App\Enums\PostStatus;
use App\Models\BlogPost;
use App\Models\User;

/**
 * §7 "Create / edit blog posts" and "Publish content" (plan §6 rows 17–22).
 */
class BlogPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('posts.create') || $user->hasPermission('posts.edit-any');
    }

    public function view(User $user, BlogPost $post): bool
    {
        return $user->hasPermission('posts.edit-any') || $this->ownsEditable($user, $post, anyStatus: true);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('posts.create');
    }

    /**
     * Editors and above edit any post, in any status. Authors edit only
     * their own, and only while it is still a draft: once it is submitted
     * for review or published, it is out of their hands (stage 6B-2 brief).
     */
    public function update(User $user, BlogPost $post): bool
    {
        return $user->hasPermission('posts.edit-any') || $this->ownsEditable($user, $post);
    }

    /**
     * Move a draft to review (the Author's hand-off to an Editor).
     */
    public function submitForReview(User $user, BlogPost $post): bool
    {
        return $post->status === PostStatus::Draft && $this->update($user, $post);
    }

    /**
     * Move between draft and review without touching Published — that is
     * `publish`. Editors (and above) move any post either way; an Author's
     * only allowed move is their own submitForReview.
     */
    public function moveStatus(User $user, BlogPost $post, PostStatus $target): bool
    {
        if ($target === PostStatus::Published || $post->status === PostStatus::Published) {
            return false;
        }

        return $user->hasPermission('posts.edit-any')
            || ($target === PostStatus::Review && $this->submitForReview($user, $post));
    }

    /**
     * Editors are withheld content.publish until §7 "approved items only" is
     * defined (plan D12). Covers both publishing and unpublishing.
     */
    public function publish(User $user, BlogPost $post): bool
    {
        return $user->hasPermission('content.publish');
    }

    public function delete(User $user, BlogPost $post): bool
    {
        return $user->hasPermission('posts.delete');
    }

    private function ownsEditable(User $user, BlogPost $post, bool $anyStatus = false): bool
    {
        return $user->hasPermission('posts.edit-own')
            && $post->author_id === $user->id
            && ($anyStatus || $post->status === PostStatus::Draft);
    }
}
