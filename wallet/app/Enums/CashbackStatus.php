<?php

namespace App\Enums;

enum CashbackStatus: string
{
    case Pending = 'pending';
    case Locked = 'locked';
    case Available = 'available';
    case Reversed = 'reversed';
    case Expired = 'expired';
}
