<?php

namespace App\Policies;

use App\Models\SystemSetting;
use App\Models\User;

class SystemSettingPolicy
{
    /**
     * Keys that should be treated as secrets (ABAC hardened).
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'lenco_api_key',
        'lenco_secret_key',
        'lenco_webhook_secret',
        'smartdata_api_key',
    ];

    public function before(User $user, string $ability): ?bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('settings.view');
    }

    public function update(User $user, SystemSetting $setting): bool
    {
        if (! $user->can('settings.update')) {
            return false;
        }

        if ($this->isSensitiveKey($setting->key)) {
            // Only super_admin passes via before(); everyone else must be denied.
            return false;
        }

        return true;
    }

    private function isSensitiveKey(?string $key): bool
    {
        if (! is_string($key) || $key === '') {
            return false;
        }

        if (in_array($key, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        $lower = strtolower($key);

        return str_contains($lower, 'secret')
            || str_contains($lower, 'token')
            || str_contains($lower, 'bearer')
            || str_ends_with($lower, '_key');
    }
}

