<?php

namespace App\Policies;

use App\Enums\KycStatus;
use App\Models\KycRecord;
use App\Models\User;

class KycRecordPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('kyc.view');
    }

    public function view(User $user, KycRecord $kyc): bool
    {
        return $user->can('kyc.view');
    }

    public function review(User $user, KycRecord $kyc): bool
    {
        if (! $user->can('kyc.review')) {
            return false;
        }

        return $kyc->status === KycStatus::Pending;
    }
}

