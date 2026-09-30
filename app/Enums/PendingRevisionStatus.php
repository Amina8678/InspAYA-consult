<?php

namespace App\Enums;

/**
 * Content approval workflow (stage 16).
 */
enum PendingRevisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
