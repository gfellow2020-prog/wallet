<?php

namespace App\Policies;

use App\Models\FraudFlag;
use App\Models\User;

class FraudFlagPolicy
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
        return $user->can('fraud.view');
    }

    public function view(User $user, FraudFlag $flag): bool
    {
        return $user->can('fraud.view');
    }

    public function resolve(User $user, FraudFlag $flag): bool
    {
        if (! $user->can('fraud.resolve')) {
            return false;
        }

        return $flag->resolved_at === null;
    }
}

