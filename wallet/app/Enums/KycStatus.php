<?php

namespace App\Enums;

enum KycStatus: string
{
    case NotSubmitted = 'not_submitted';
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
