<?php

namespace App\Enums;

enum WithdrawalStatus: string
{
    case Requested = 'requested';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Processing = 'processing';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Reversed = 'reversed';
}
