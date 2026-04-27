<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected OtpService $otp,
    ) {}

    private function normalizeZambiaPhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '260') && strlen($digits) === 12) {
            return $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '260'.substr($digits, 1);
        }
        if (strlen($digits) === 9) {
            return '260'.$digits;
        }

        return null;
    }

    /**
     * Resolve identifier to user. Identifier may be an email or phone number.
     */
    private function userByIdentifier(string $identifier): ?User
    {
        $id = trim($identifier);
        if ($id === '') {
            return null;
        }

        if (filter_var($id, FILTER_VALIDATE_EMAIL)) {
            return User::query()->where('email', strtolower($id))->first();
        }

        $phone = $this->normalizeZambiaPhone($id) ?? preg_replace('/\D+/', '', $id) ?? '';
        if ($phone === '') {
            return null;
        }

        return User::query()->where('phone_number', $phone)->first();
    }

    private function passwordResetSessionCacheKey(string $token): string
    {
        return 'pwd_reset_session:'.hash('sha256', $token);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'nrc_number' => $user->nrc_number,
            // Public handle users share so others can send them money /
            // target them in Buy-for-Me requests.
            'extracash_number' => $user->extracash_number,
            'profile_photo_url' => $user->profile_photo_path
                ? url('/storage/'.ltrim($user->profile_photo_path, '/'))
                : null,
            'created_at' => $user->created_at,
            'has_open_fraud_review' => $user->hasOpenFraudFlags(),
        ];
    }

    private function nrcSettings(): array
    {
        $settings = Cache::remember('system_settings', 300, function () {
            return SystemSetting::pluck('value', 'key')->toArray();
        });

        return [
            'api_key' => $settings['smartdata_api_key'] ?? config('services.smartdata.api_key'),
            'base_url' => rtrim(
                $settings['smartdata_base_url'] ?? config('services.smartdata.base_url', 'https://mysmartdata.tech/api/v1'),
                '/'
            ),
        ];
    }

    /**
     * SmartData / NRC providers return TPIN under varying keys, casing, or nested objects.
     */
    private function extractTpinFromNrcProviderPayload(array $payload, array $providerData): ?string
    {
        $root = $payload['tpin'] ?? null;
        if (is_string($root) || is_numeric($root)) {
            $s = trim((string) $root);
            if ($s !== '') {
                return $s;
            }
        }

        $directKeys = [
            'tpin', 'TPIN', 'Tpin', 't_pin', 'T_PIN',
            'tin', 'TIN', 'TPin',
            'taxpayer_number', 'tax_payer_number', 'taxpayer_no', 'taxpayerNo', 'taxpayer_id',
            'zra_tpin', 'ZRA_TPIN', 'ZRA_Tpin', 'TPINNo', 'tpin_no', 'tpinNo',
            'tax_identification_number', 'tax_id',
        ];
        foreach ($directKeys as $k) {
            if (! array_key_exists($k, $providerData)) {
                continue;
            }
            $v = $providerData[$k];
            if (is_string($v) || is_numeric($v)) {
                $s = trim((string) $v);
                if ($s !== '') {
                    return $s;
                }
            }
        }

        return $this->extractTpinFromNestedArray($providerData, 0);
    }

    private function extractTpinFromNestedArray(mixed $node, int $depth): ?string
    {
        if ($depth > 5 || ! is_array($node)) {
            return null;
        }
        $keyAliases = [
            'tpin', 'TPIN', 'Tpin', 'tin', 'TIN', 't_pin', 'taxpayer_number', 'tax_payer_number',
            'taxpayer_no', 'taxpayerNo', 'zra_tpin', 'TPINNo', 'tpinNo',
        ];
        foreach ($keyAliases as $k) {
            if (! array_key_exists($k, $node)) {
                continue;
            }
            $v = $node[$k];
            if (is_string($v) || is_numeric($v)) {
                $s = trim((string) $v);
                if ($s !== '') {
                    return $s;
                }
            }
        }
        foreach ($node as $child) {
            if (is_array($child)) {
                $found = $this->extractTpinFromNestedArray($child, $depth + 1);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function walletPayload($wallet): ?array
    {
        if (! $wallet) {
            return null;
        }

        return [
            'balance' => (float) ($wallet->available_balance ?? $wallet->balance ?? 0),
            'available_balance' => (float) ($wallet->available_balance ?? $wallet->balance ?? 0),
            'pending_balance' => (float) ($wallet->pending_balance ?? 0),
            'currency' => $wallet->currency,
            'card_number' => $wallet->card_number ?: ('**** **** **** '.str_pad(substr((string) $wallet->id, -4), 4, '0', STR_PAD_LEFT)),
            'expiry' => $wallet->expiry ?: now()->addYears(4)->format('m/y'),
        ];
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone_number' => 'required|string|max:20',
            'nrc_number' => 'required|string|max:30',
            'tpin' => 'required|string|max:30',
            'profile_photo' => 'required|image|mimes:jpeg,jpg,png|max:10240',
        ]);

        $phone = preg_replace('/\D+/', '', (string) $data['phone_number']) ?? '';
        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            $phone = '260'.substr($phone, 1);
        } elseif (strlen($phone) === 9) {
            $phone = '260'.$phone;
        }
        if (! (str_starts_with($phone, '260') && strlen($phone) === 12)) {
            return response()->json(['message' => 'Enter a valid Zambian mobile number.'], 422);
        }

        $photoPath = $request->file('profile_photo')->store('profile-photos', 'public');

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone_number' => $phone,
            'phone_verified_at' => null,
            'nrc_number' => $data['nrc_number'],
            'tpin' => $data['tpin'],
            'profile_photo_path' => $photoPath,
        ]);

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'available_balance' => 0,
                'pending_balance' => 0,
                'currency' => 'ZMW',
            ]
        );

        $challenge = $this->otp->createAndSend($user, 'phone_verify', [
            'phone_number' => $phone,
        ]);

        return response()->json([
            'otp_required' => true,
            'otp' => [
                'id' => $challenge['otp']->id,
                'purpose' => 'phone_verify',
                'expires_at' => $challenge['otp']->expires_at?->toIso8601String(),
                'sent_via' => $challenge['sent_via'],
            ],
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'phone_number' => $phone,
            ],
            'wallet' => $this->walletPayload($wallet),
        ], 201);
    }

    public function verifyRegisterOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'otp_id' => 'required|integer',
            'otp_code' => 'required|string|max:12',
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $data['email'])->first();
        if (! $user) {
            return response()->json(['message' => 'Invalid OTP.'], 422);
        }

        $ok = $this->otp->verify($user, (int) $data['otp_id'], 'phone_verify', (string) $data['otp_code']);
        if (! $ok) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        $user->forceFill([
            'phone_verified_at' => now(),
        ])->save();

        $token = $user->createToken('mobile')->plainTextToken;
        $wallet = $user->load('wallet')->wallet;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
            'wallet' => $this->walletPayload($wallet),
        ]);
    }

    public function verifyNrc(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nrc_number' => 'required|string|max:30',
        ]);

        $settings = $this->nrcSettings();

        if (empty($settings['api_key'])) {
            return response()->json([
                'success' => false,
                'message' => 'NRC verification API key is not configured.',
            ], 422);
        }

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $settings['api_key'],
            ])->post($settings['base_url'].'/nrc/verify', [
                'nrc_number' => $data['nrc_number'],
            ]);

            $payload = $response->json() ?? [];
            $providerData = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $tpin = $this->extractTpinFromNrcProviderPayload($payload, $providerData);
            if ($tpin === null && is_string($payload['data'] ?? null)) {
                $maybe = json_decode($payload['data'], true);
                if (is_array($maybe)) {
                    $tpin = $this->extractTpinFromNrcProviderPayload($payload, $maybe);
                }
            }

            return response()->json([
                'success' => (bool) ($payload['success'] ?? false),
                'reference' => $payload['reference'] ?? null,
                'id_number' => $payload['id_number'] ?? $data['nrc_number'],
                'data' => [
                    'full_name' => $providerData['full_name'] ?? null,
                    'first_name' => $providerData['first_name'] ?? null,
                    'last_name' => $providerData['last_name'] ?? null,
                    'tpin' => $tpin !== null ? (string) $tpin : null,
                ],
                'message' => $payload['message'] ?? ($response->successful() ? 'Verification complete.' : 'Verification failed.'),
            ], $response->status());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to verify NRC at the moment. Please try again.',
            ], 502);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_id' => 'nullable|string|max:128',
        ]);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->phone_verified_at === null) {
            $challenge = $this->otp->createAndSend($user, 'phone_verify', [
                'phone_number' => $user->phone_number,
            ]);

            return response()->json([
                'otp_required' => true,
                'otp' => [
                    'id' => $challenge['otp']->id,
                    'purpose' => 'phone_verify',
                    'expires_at' => $challenge['otp']->expires_at?->toIso8601String(),
                    'sent_via' => $challenge['sent_via'],
                ],
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                ],
            ], 428);
        }

        $deviceId = is_string($data['device_id'] ?? null) ? trim((string) $data['device_id']) : '';
        if ($deviceId === '') {
            $deviceId = (string) ($request->header('X-Device-Id') ?? '');
        }

        $ip = (string) $request->ip();
        $risk = false;
        if (is_string($user->last_login_ip) && $user->last_login_ip !== '' && $user->last_login_ip !== $ip) {
            $risk = true;
        }
        if ($deviceId !== '' && is_string($user->last_login_device_id) && $user->last_login_device_id !== '' && $user->last_login_device_id !== $deviceId) {
            $risk = true;
        }

        // First login on record: allow, then bind device/IP on success.

        if ($risk) {
            $challenge = $this->otp->createAndSend($user, 'login', [
                'ip' => $ip,
                'device_id' => $deviceId !== '' ? $deviceId : null,
            ]);

            return response()->json([
                'otp_required' => true,
                'otp' => [
                    'id' => $challenge['otp']->id,
                    'purpose' => 'login',
                    'expires_at' => $challenge['otp']->expires_at?->toIso8601String(),
                    'sent_via' => $challenge['sent_via'],
                ],
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ], 428);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        $user->forceFill([
            'last_login_ip' => $ip,
            'last_login_device_id' => $deviceId !== '' ? $deviceId : ($user->last_login_device_id ?? null),
            'last_login_at' => now(),
        ])->save();

        $wallet = $user->load('wallet')->wallet;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
            'wallet' => $this->walletPayload($wallet),
        ]);
    }

    public function verifyLoginOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'otp_id' => 'required|integer',
            'otp_code' => 'required|string|max:12',
            'device_id' => 'nullable|string|max:128',
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $data['email'])->first();
        if (! $user) {
            return response()->json(['message' => 'Invalid OTP.'], 422);
        }

        $ok = $this->otp->verify($user, (int) $data['otp_id'], 'login', (string) $data['otp_code']);
        if (! $ok) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        $deviceId = is_string($data['device_id'] ?? null) ? trim((string) $data['device_id']) : '';
        if ($deviceId === '') {
            $deviceId = (string) ($request->header('X-Device-Id') ?? '');
        }

        $token = $user->createToken('mobile')->plainTextToken;

        $user->forceFill([
            'last_login_ip' => (string) $request->ip(),
            'last_login_device_id' => $deviceId !== '' ? $deviceId : ($user->last_login_device_id ?? null),
            'last_login_at' => now(),
        ])->save();

        $wallet = $user->load('wallet')->wallet;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
            'wallet' => $this->walletPayload($wallet),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function requestPasswordResetOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => 'required|string|max:255',
        ]);

        $user = $this->userByIdentifier((string) $data['identifier']);
        if (! $user) {
            // Avoid account enumeration.
            return response()->json(['message' => 'If the account exists, an OTP has been sent.'], 200);
        }

        $challenge = $this->otp->createAndSend($user, 'password_reset', [
            'ip' => (string) $request->ip(),
        ]);

        return response()->json([
            'otp_required' => true,
            'otp' => [
                'id' => $challenge['otp']->id,
                'purpose' => 'password_reset',
                'expires_at' => $challenge['otp']->expires_at?->toIso8601String(),
                'sent_via' => $challenge['sent_via'],
            ],
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ]);
    }

    public function verifyPasswordResetOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'otp_id' => 'required|integer',
            'otp_code' => 'required|string|max:12',
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', strtolower((string) $data['email']))->first();
        if (! $user) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        $ok = $this->otp->verify($user, (int) $data['otp_id'], 'password_reset', (string) $data['otp_code']);
        if (! $ok) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        $token = Str::random(64);
        Cache::put(
            $this->passwordResetSessionCacheKey($token),
            ['user_id' => $user->id],
            now()->addMinutes(15)
        );

        return response()->json([
            'reset_session' => $token,
        ]);
    }

    public function resetPasswordWithSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reset_session' => 'required|string|max:255',
            'password' => 'required|string|min:8',
        ]);

        $session = Cache::pull($this->passwordResetSessionCacheKey((string) $data['reset_session']));
        $userId = is_array($session) ? (int) ($session['user_id'] ?? 0) : 0;
        if ($userId <= 0) {
            return response()->json(['message' => 'Reset session expired. Please request a new OTP.'], 422);
        }

        /** @var User|null $user */
        $user = User::query()->find($userId);
        if (! $user) {
            return response()->json(['message' => 'Reset session expired. Please request a new OTP.'], 422);
        }

        if (Hash::check((string) $data['password'], (string) $user->password)) {
            return response()->json([
                'message' => 'Choose a new password you haven’t used before.',
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make((string) $data['password']),
        ])->save();

        $token = $user->createToken('mobile')->plainTextToken;
        $wallet = $user->load('wallet')->wallet;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
            'wallet' => $this->walletPayload($wallet),
        ]);
    }
}
