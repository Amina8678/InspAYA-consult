<?php

namespace App\Enums;

/**
 * Contact enquiry lifecycle (FR-CONT-06).
 */
enum EnquiryStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Responded = 'responded';
    case Closed = 'closed';
}
