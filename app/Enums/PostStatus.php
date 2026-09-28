<?php

namespace App\Enums;

/**
 * Blog workflow (FR-BLOG-02). "Scheduled" is deferred; see schema plan §7.
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
}
