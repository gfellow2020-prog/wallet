<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\LencoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class SettingsController extends Controller
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'lenco_api_key',
        'lenco_secret_key',
        'lenco_webhook_secret',
        'smartdata_api_key',
        'sms_username',
        'sms_password',
        'sms_sender_id',
    ];

    protected array $settingKeys = [
        // Lenco
        'lenco_api_key',
        'lenco_secret_key',
        'lenco_base_url',
        'lenco_webhook_secret',
        'lenco_environment',
        'lenco_account_id',
        'lenco_country',
        // Cashback
        'cashback_enabled',
        'cashback_rate',
        'cashback_hold_days',
        'cashback_daily_cap',
        'cashback_monthly_cap',
        // Withdrawals
        'withdrawal_min_amount',
        'withdrawal_daily_max',
        'withdrawal_require_kyc',
        // NRC Verification (SmartData)
        'smartdata_api_key',
        'smartdata_base_url',
        // SMS (OTP)
        'sms_enabled',
        'sms_username',
        'sms_password',
        'sms_sender_id',
        'sms_short_code',
    ];

    public function index()
    {
        Gate::authorize('viewAny', SystemSetting::class);

        $settings = SystemSetting::whereIn('key', $this->settingKeys)
            ->pluck('value', 'key')
            ->toArray();

        $lenco = app(LencoService::class);
        $lencoCallbackUrls = array_merge(
            $lenco->adminListedCallbackUrls(),
            ['Optional: generic path (e.g. Lenco dashboard webhooks)' => url('/webhook/lenco/ingest')],
        );

        return view('admin.settings.index', compact('settings', 'lencoCallbackUrls'));
    }

    public function update(Request $request)
    {
        Gate::authorize('update', new SystemSetting(['key' => '']));

        $request->validate([
            'settings' => 'required|array',
            'settings.lenco_environment' => 'nullable|in:sandbox,live',
            'settings.lenco_base_url' => 'nullable|string|url|max:512',
            'settings.lenco_api_key' => 'nullable|string|max:512',
            'settings.lenco_secret_key' => 'nullable|string|max:1024',
            'settings.lenco_webhook_secret' => 'nullable|string|max:1024',
            'settings.lenco_account_id' => 'nullable|string|max:64',
            'settings.lenco_country' => 'nullable|string|size:2',
            'settings.smartdata_base_url' => 'nullable|string|url|max:512',
            'settings.smartdata_api_key' => 'nullable|string|max:1024',
            'settings.sms_enabled' => 'nullable|in:0,1',
            'settings.sms_username' => 'nullable|string|max:128',
            'settings.sms_password' => 'nullable|string|max:256',
            'settings.sms_sender_id' => 'nullable|string|max:32',
            'settings.sms_short_code' => 'nullable|string|max:32',
        ]);

        $data = $request->input('settings', []);
        $existing = SystemSetting::whereIn('key', $this->settingKeys)
            ->pluck('value', 'key')
            ->all();

        foreach ($this->settingKeys as $key) {
            $setting = SystemSetting::firstOrNew(['key' => $key]);
            $value = $data[$key] ?? null;

            if (in_array($key, ['cashback_enabled', 'withdrawal_require_kyc'], true)) {
                // Form sends hidden "0" when unchecked, or "1" from checkbox (last value wins if both present)
                $raw = $data[$key] ?? '0';
                $value = ((string) $raw === '1' || $raw === 1) ? '1' : '0';
            } elseif (in_array($key, self::SENSITIVE_KEYS, true) && ($value === null || $value === '')) {
                // Global ConvertEmptyStringsToNull turns "" into null — keep prior value if user did not re-enter
                // For non-super-admins, treat this as a no-op (preserve current secret without touching it).
                if (! $request->user()?->hasRole('super_admin')) {
                    continue;
                }

                $value = $existing[$key] ?? '';
            } else {
                if ($value === null) {
                    $value = '';
                } else {
                    $value = (string) $value;
                }
            }

            Gate::authorize('update', $setting);

            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        Cache::forget('system_settings');

        return redirect()->route('admin.settings')
            ->with('success', 'Settings saved successfully.');
    }

    /**
     * Verify saved Lenco credentials with a no-op collection status lookup (same pattern as Landlord superadmin test).
     */
    public function testLenco(LencoService $lenco): RedirectResponse
    {
        if (! $lenco->configured()) {
            return redirect()
                ->route('admin.settings')
                ->with('error', 'Lenco is not fully configured. Save a non-empty API key, secret key, and base URL, then test again.');
        }

        $reference = 'TEST-CONNECTION-'.now()->timestamp;

        try {
            $result = $lenco->verifyCollection($reference);
        } catch (\Throwable $e) {
            Log::error('Lenco connection test failed', ['error' => $e->getMessage()]);

            return redirect()
                ->route('admin.settings')
                ->with('error', 'Could not connect to Lenco API. Check your base URL, network, and that SSL is valid.');
        }

        if (! empty($result['success'])) {
            return redirect()
                ->route('admin.settings')
                ->with('success', 'Lenco API connection successful! The gateway responded to a status check.');
        }

        $message = (string) ($result['message'] ?? '');
        $lower = mb_strtolower($message);

        if (str_contains($lower, 'not found')
            || str_contains($lower, 'invalid')
            || str_contains($lower, 'does not exist')
            || str_contains($lower, 'no such')
            || str_contains($lower, 'unknown reference')
        ) {
            return redirect()
                ->route('admin.settings')
                ->with('success', 'Lenco API connection successful! Your API keys are valid (expected "not found" for a test reference).');
        }

        if (str_contains($lower, 'unauthorized') || str_contains($lower, 'invalid token') || str_contains($lower, '401')) {
            return redirect()
                ->route('admin.settings')
                ->with('error', 'Lenco API authentication failed. Check your API key, secret, and that Sandbox/Live match your keys.');
        }

        return redirect()
            ->route('admin.settings')
            ->with('error', 'Lenco API error: '.$message);
    }
}
