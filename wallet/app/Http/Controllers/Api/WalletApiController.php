<?php

namespace App\Http\Controllers\Api;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PayoutAccountResource;
use App\Models\Deposit;
use App\Models\PayoutAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletLedger;
use App\Models\Withdrawal;
use App\Services\OtpService;
use App\Services\ExtraCashGatewayService;
use App\Services\FraudFlagService;
use App\Services\LedgerService;
use App\Services\LencoService;
use App\Services\NotificationService;
use App\Services\RewardsEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WalletApiController extends Controller
{
    public function __construct(
        protected LedgerService $ledger,
        protected ExtraCashGatewayService $extracashGateway,
        protected LencoService $lenco,
        protected RewardsEngine $rewards,
        protected FraudFlagService $fraudFlags,
        protected NotificationService $notifications,
        protected OtpService $otp,
    ) {}

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'nrc_number' => $user->nrc_number,
            'extracash_number' => $user->extracash_number,
            'profile_photo_url' => $user->profile_photo_path
                ? url('/storage/'.ltrim($user->profile_photo_path, '/'))
                : null,
            'created_at' => $user->created_at,
            'has_open_fraud_review' => $user->hasOpenFraudFlags(),
        ];
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

    private function normalizePhoneNumber(string $raw): ?string
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

    private function extractLookupName(array $payload): ?string
    {
        $candidate = $payload['full_name']
            ?? $payload['name']
            ?? $payload['account_name']
            ?? $payload['customer_name']
            ?? null;

        return is_string($candidate) && trim($candidate) !== '' ? trim($candidate) : null;
    }

    private function recordRewardActionSafely(User $user, string $actionType, array $source): void
    {
        try {
            $this->rewards->recordAction($user, $actionType, $source);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load(['wallet']);

        // Always get fresh wallet balance from database (bypasses cache)
        $wallet = $user->wallet?->fresh();

        return response()->json([
            'user' => $this->userPayload($user),
            'wallet' => $this->walletPayload($wallet),
        ]);
    }

    public function updateProfilePhoto(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'profile_photo' => 'required|image|mimes:jpeg,jpg,png|max:10240',
        ]);

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $path = $data['profile_photo']->store('profile-photos', 'public');
        $user->update(['profile_photo_path' => $path]);

        return response()->json([
            'message' => 'Profile photo updated successfully.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load('wallet');
        $wallet = $user->wallet;

        $perPage = max(1, min((int) $request->integer('per_page', 20), 50));

        if (! $wallet) {
            return response()->json([
                'transactions' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
            ]);
        }

        $page = $wallet->ledgers()->paginate($perPage);
        $transactions = collect($page->items())->map(fn ($t) => [
            'id' => $t->id,
            'type' => $t->type,
            'amount' => $t->amount,
            'narration' => isset($t->metadata->product_title) ? $t->metadata->product_title : ucwords(str_replace('_', ' ', $t->type)),
            'status' => 'completed',
            'date' => $t->created_at?->toISOString(),
        ])->values();

        return response()->json([
            'transactions' => $transactions,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function nameLookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_number' => 'required|string|max:20',
        ]);

        $phoneNumber = $this->normalizePhoneNumber($data['phone_number']);

        if (! $phoneNumber) {
            return response()->json(['message' => 'Enter a valid Zambian mobile number.'], 422);
        }

        $result = $this->extracashGateway->nameLookup($phoneNumber);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'message' => $result['message'] ?? 'Unable to resolve recipient name.',
            ], 422);
        }

        $name = $this->extractLookupName($result['data'] ?? []);

        return response()->json([
            'name' => $name ?? 'Unknown recipient',
            'phone_number' => $phoneNumber,
        ]);
    }

    public function sendMoney(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'OTP is required for send money.',
            'otp_required' => true,
        ], 428);
    }

    public function requestSendMoneyOtp(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $wallet = $user->wallet;

        if (! $wallet) {
            return response()->json(['message' => 'Wallet not found.'], 404);
        }

        $data = $request->validate([
            'phone_number' => 'required|string|max:20',
            'amount' => 'required|numeric|min:1',
            'recipient' => 'nullable|string|max:120',
        ]);

        $phoneNumber = $this->normalizePhoneNumber($data['phone_number']);
        if (! $phoneNumber) {
            return response()->json(['message' => 'Enter a valid Zambian mobile number.'], 422);
        }

        $amount = (float) $data['amount'];
        if ((float) $wallet->available_balance < $amount) {
            return response()->json(['message' => 'Insufficient available balance.'], 422);
        }

        $recipientName = trim((string) ($data['recipient'] ?? ''));
        if ($recipientName === '') {
            $lookup = $this->extracashGateway->nameLookup($phoneNumber);
            if ($lookup['success'] ?? false) {
                $recipientName = $this->extractLookupName($lookup['data'] ?? []) ?? $phoneNumber;
            } else {
                $recipientName = $phoneNumber;
            }
        }

        $challenge = $this->otp->createAndSend($user, 'send_money', [
            'to_phone' => $phoneNumber,
            'amount' => $amount,
            'recipient' => $recipientName,
        ]);

        return response()->json([
            'message' => 'OTP sent.',
            'otp' => [
                'id' => $challenge['otp']->id,
                'purpose' => $challenge['otp']->purpose,
                'expires_at' => $challenge['otp']->expires_at?->toIso8601String(),
                'sent_via' => $challenge['sent_via'],
            ],
        ], 201);
    }

    public function verifySendMoneyOtp(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $wallet = $user->wallet;

        if (! $wallet) {
            return response()->json(['message' => 'Wallet not found.'], 404);
        }

        $data = $request->validate([
            'otp_id' => 'required|integer',
            'otp_code' => 'required|string|max:12',
        ]);

        // Load the OTP row to recover context for idempotent execution.
        $otp = \App\Models\UserOtp::query()
            ->where('id', (int) $data['otp_id'])
            ->where('user_id', $user->id)
            ->where('purpose', 'send_money')
            ->first();

        if (! $otp) {
            return response()->json(['message' => 'OTP challenge not found.'], 404);
        }

        $ctx = is_array($otp->context) ? $otp->context : [];
        $toPhone = is_string($ctx['to_phone'] ?? null) ? (string) $ctx['to_phone'] : null;
        $amount = is_numeric($ctx['amount'] ?? null) ? (float) $ctx['amount'] : null;
        $recipientName = is_string($ctx['recipient'] ?? null) ? (string) $ctx['recipient'] : null;

        if (! $toPhone || ! $amount || $amount <= 0) {
            return response()->json(['message' => 'OTP challenge is missing required context.'], 422);
        }

        $ok = $this->otp->verify($user, (int) $otp->id, 'send_money', (string) $data['otp_code']);
        if (! $ok) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        if ((float) $wallet->available_balance < $amount) {
            return response()->json(['message' => 'Insufficient available balance.'], 422);
        }

        $narration = 'Sent to '.($recipientName ?: $toPhone);
        $result = $this->extracashGateway->disburse($toPhone, $amount, $narration);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'message' => $result['message'] ?? 'Disbursement failed.',
            ], 422);
        }

        $transaction = DB::transaction(function () use ($wallet, $amount, $toPhone, $narration, $result) {
            $lockedWallet = $wallet->newQuery()->lockForUpdate()->findOrFail($wallet->id);
            $lockedWallet->decrement('available_balance', $amount);

            return $lockedWallet->transactions()->create([
                'type' => 'debit',
                'amount' => $amount,
                'narration' => $narration,
                'phone_number' => $toPhone,
                'gateway_reference' => $result['reference'] ?? null,
                'gateway_status' => 'pending',
                'transacted_at' => now(),
            ]);
        });

        $this->recordRewardActionSafely($user, 'send_money', [
            'source_type' => Transaction::class,
            'source_id' => $transaction->id,
            'phone_number' => $toPhone,
        ]);

        $this->fraudFlags->checkSendVelocity($user, $wallet->id);

        Log::info('wallet.send_money', [
            'transaction_id' => $transaction->id,
            'gateway_reference' => $result['reference'] ?? null,
            'amount' => $amount,
        ]);

        $this->notifications->notifyUser(
            $user,
            'send_money',
            'Money sent',
            sprintf('You sent K%.2f to %s.', $amount, $toPhone),
            ['transaction_id' => $transaction->id, 'reference' => $result['reference'] ?? null],
            sendEmail: false,
            sendPush: true,
        );

        return response()->json([
            'message' => 'Transfer successful',
            'reference' => $result['reference'] ?? null,
            'recipient' => [
                'name' => $recipientName ?: $toPhone,
                'phone_number' => $toPhone,
            ],
            'wallet' => $this->walletPayload($wallet->fresh()),
        ]);
    }

    public function legacySendMoney(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'This endpoint now requires OTP. Use /wallet/send/otp/request then /wallet/send/otp/verify.',
        ], 410);
    }

    /* ──────────────────────────────────────────────
     |  Deposits (Top-up via Lenco)
     |────────────────────────────────────────────── */

    /**
     * Initiate a deposit (collection) via Lenco.
     *
     * POST /api/wallet/deposit
     */
    public function deposit(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $wallet = $user->wallet;

        if (! $wallet) {
            return response()->json(['message' => 'Wallet not found.'], 404);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'provider' => 'required|in:mobile_money,bank',
            'phone_number' => 'required_if:provider,mobile_money|nullable|string',
            'account_number' => 'required_if:provider,bank|nullable|string',
            'account_name' => 'nullable|string',
            'bank_code' => 'nullable|string',
            'narration' => 'nullable|string|max:255',
        ]);

        if (! $this->lenco->configured()) {
            return response()->json(['message' => 'Lenco is not configured. Contact support.'], 503);
        }

        $reference = (string) Str::uuid();

        $deposit = Deposit::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'reference' => $reference,
            'amount' => $data['amount'],
            'currency' => $wallet->currency ?? 'ZMW',
            'provider' => $data['provider'],
            'phone_number' => $data['phone_number'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_name' => $data['account_name'] ?? null,
            'bank_code' => $data['bank_code'] ?? null,
            'status' => 'pending',
            'narration' => $data['narration'] ?? 'Wallet deposit',
        ]);

        // Call Lenco to initiate the collection
        $result = $this->lenco->collect([
            'amount' => (float) $data['amount'],
            'currency' => $wallet->currency ?? 'ZMW',
            'provider' => $data['provider'],
            'phone_number' => $data['phone_number'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_name' => $data['account_name'] ?? null,
            'bank_code' => $data['bank_code'] ?? null,
            'narration' => $data['narration'] ?? 'Wallet deposit',
            'metadata' => [
                'deposit_id' => $deposit->id,
                'user_id' => $user->id,
                'reference' => $reference,
            ],
        ]);

        // Store Lenco reference for status tracking
        if (! empty($result['reference'])) {
            $deposit->update(['lenco_reference' => $result['reference']]);
        }

        if (! $result['success']) {
            $deposit->update(['status' => 'failed']);

            $this->notifications->notifyUser(
                $user,
                'deposit_failed',
                'Deposit failed',
                'Your deposit could not be initiated. Please try again.',
                ['deposit_id' => $deposit->id, 'reference' => $reference],
                sendEmail: false,
                sendPush: true,
            );

            return response()->json([
                'message' => $result['message'] ?? 'Deposit initiation failed.',
                'reference' => $reference,
            ], 422);
        }

        $this->notifications->notifyUser(
            $user,
            'deposit_pending',
            'Deposit initiated',
            'Your deposit was initiated. Complete the payment on your device.',
            ['deposit_id' => $deposit->id, 'reference' => $reference, 'lenco_reference' => $result['reference'] ?? null],
            sendEmail: false,
            sendPush: true,
        );

        return response()->json([
            'message' => 'Deposit initiated. Complete the payment on your device.',
            'reference' => $reference,
            'lenco_reference' => $result['reference'] ?? null,
            'status' => 'pending',
        ]);
    }

    /**
     * List the authenticated user's deposits.
     *
     * GET /api/wallet/deposits
     */
    public function deposits(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deposits = Deposit::where('user_id', $user->id)
            ->latest()
            ->paginate(20);

        return response()->json($deposits);
    }

    /* ──────────────────────────────────────────────
     |  Withdrawals (Cash-out via Lenco)
     |────────────────────────────────────────────── */

    /**
     * Initiate a withdrawal (disbursement) via Lenco using a saved payout account.
     *
     * POST /api/wallet/withdraw
     */
    public function withdraw(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $wallet = $user->wallet;

        if (! $wallet) {
            return response()->json(['message' => 'Wallet not found.'], 404);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:5',
            'payout_account_id' => 'required|exists:payout_accounts,id',
            'narration' => 'nullable|string|max:255',
        ]);

        $payout = PayoutAccount::where('user_id', $user->id)
            ->where('id', $data['payout_account_id'])
            ->first();

        if (! $payout) {
            return response()->json(['message' => 'Payout account not found.'], 404);
        }

        $amount = (float) $data['amount'];

        // Check balance
        if ((float) $wallet->available_balance < $amount) {
            return response()->json(['message' => 'Insufficient available balance.'], 422);
        }

        if (! $this->lenco->configured()) {
            return response()->json(['message' => 'Lenco is not configured. Contact support.'], 503);
        }

        $reference = (string) Str::uuid();

        // Debit wallet immediately (hold the funds)
        try {
            $this->ledger->debit(
                $wallet,
                'withdrawal',
                $amount,
                Withdrawal::class,
                0,
                ['reference' => $reference, 'status' => 'pending']
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $wallet->currency ?? 'ZMW',
            'provider' => $payout->type,
            'phone_number' => $payout->phone_number,
            'account_number' => $payout->account_number,
            'account_name' => $payout->account_name,
            'bank_code' => $payout->bank_code,
            'status' => WithdrawalStatus::Requested,
            'narration' => $data['narration'] ?? 'Wallet withdrawal',
        ]);

        $this->fraudFlags->checkWithdrawalVelocity($user);

        // Call Lenco to initiate the disbursement
        $result = $this->lenco->disburse([
            'amount' => $amount,
            'currency' => $wallet->currency ?? 'ZMW',
            'provider' => $payout->type,
            'phone_number' => $payout->phone_number,
            'account_number' => $payout->account_number,
            'account_name' => $payout->account_name,
            'bank_code' => $payout->bank_code,
            'narration' => $data['narration'] ?? 'Wallet withdrawal',
            'metadata' => [
                'withdrawal_id' => $withdrawal->id,
                'user_id' => $user->id,
                'reference' => $reference,
            ],
        ]);

        if (! empty($result['reference'])) {
            $withdrawal->update(['lenco_reference' => $result['reference']]);
        }

        if (! $result['success']) {
            // Refund the wallet since Lenco failed
            $this->ledger->credit(
                $wallet->fresh(),
                'withdrawal_refund',
                $amount,
                Withdrawal::class,
                $withdrawal->id,
                ['reference' => $reference, 'reason' => 'Lenco disbursement failed']
            );

            $withdrawal->update([
                'status' => WithdrawalStatus::Rejected,
                'rejection_reason' => $result['message'] ?? 'Lenco disbursement failed',
            ]);

            $this->notifications->notifyUser(
                $user,
                'withdrawal_failed',
                'Withdrawal failed',
                'Your withdrawal failed and funds were refunded.',
                ['withdrawal_id' => $withdrawal->id, 'reference' => $reference],
                sendEmail: true,
                sendPush: true,
            );

            return response()->json([
                'message' => $result['message'] ?? 'Withdrawal failed. Funds have been refunded.',
                'reference' => $reference,
            ], 422);
        }

        $withdrawal->update(['status' => WithdrawalStatus::Processing]);
        $wallet->increment('lifetime_withdrawn', $amount);

        $this->notifications->notifyUser(
            $user,
            'withdrawal_processing',
            'Withdrawal processing',
            sprintf('Your withdrawal of K%.2f is being processed.', $amount),
            ['withdrawal_id' => $withdrawal->id, 'reference' => $reference, 'lenco_reference' => $result['reference'] ?? null],
            sendEmail: false,
            sendPush: true,
        );

        return response()->json([
            'message' => 'Withdrawal initiated successfully.',
            'reference' => $reference,
            'lenco_reference' => $result['reference'] ?? null,
            'status' => 'approved',
        ]);
    }

    /**
     * List the authenticated user's withdrawals.
     *
     * GET /api/wallet/withdrawals
     */
    public function withdrawals(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $items = Withdrawal::where('user_id', $user->id)
            ->latest()
            ->paginate(20);

        return response()->json($items);
    }

    /* ──────────────────────────────────────────────
     |  Lenco Utilities
     |────────────────────────────────────────────── */

    /**
     * Get supported banks from Lenco.
     *
     * GET /api/lenco/banks
     */
    public function lencoBanks(Request $request): JsonResponse
    {
        if (! $this->lenco->configured()) {
            return response()->json(['message' => 'Lenco is not configured.'], 503);
        }

        $result = $this->lenco->banks();

        if (! $result['success']) {
            return response()->json(['message' => $result['message'] ?? 'Could not fetch banks.'], 502);
        }

        return response()->json(['banks' => $result['data']]);
    }

    /**
     * Resolve a bank account (validate account number + bank code).
     *
     * POST /api/lenco/resolve-account
     */
    public function lencoResolveAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_number' => 'required|string',
            'bank_code' => 'required|string',
        ]);

        if (! $this->lenco->configured()) {
            return response()->json(['message' => 'Lenco is not configured.'], 503);
        }

        $result = $this->lenco->resolveAccount($data['account_number'], $data['bank_code']);

        if (! $result['success']) {
            return response()->json(['message' => $result['message'] ?? 'Could not resolve account.'], 422);
        }

        return response()->json($result['data']);
    }

    /* ──────────────────────────────────────────────
     |  Payout Accounts (Bank / Mobile Money)
     |────────────────────────────────────────────── */

    /**
     * List the authenticated user's payout accounts.
     *
     * GET /api/payout-accounts
     */
    public function payoutAccounts(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $perPage = max(1, min((int) $request->integer('per_page', 20), 50));
        $page = PayoutAccount::where('user_id', $user->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'accounts' => PayoutAccountResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Store a new payout account.
     *
     * POST /api/payout-accounts
     */
    public function storePayoutAccount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'type' => 'required|in:bank,mobile_money',
            'bank_name' => 'required_if:type,bank|nullable|string|max:255',
            'bank_code' => 'nullable|string|max:50',
            'account_number' => 'required_if:type,bank|nullable|string|max:50',
            'account_name' => 'required_if:type,bank|nullable|string|max:255',
            'phone_number' => 'required_if:type,mobile_money|nullable|string|max:20',
            'is_default' => 'nullable|boolean',
        ]);

        // If setting as default, unset others
        if (! empty($data['is_default'])) {
            PayoutAccount::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $account = PayoutAccount::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'bank_name' => $data['bank_name'] ?? null,
            'bank_code' => $data['bank_code'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_name' => $data['account_name'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'is_default' => $data['is_default'] ?? false,
        ]);

        return response()->json([
            'message' => 'Payout account added successfully.',
            'account' => $account,
        ]);
    }

    /**
     * Delete a payout account.
     *
     * DELETE /api/payout-accounts/{id}
     */
    public function destroyPayoutAccount(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $account = PayoutAccount::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $account) {
            return response()->json(['message' => 'Payout account not found.'], 404);
        }

        $account->delete();

        return response()->json(['message' => 'Payout account removed.']);
    }

    /* ──────────────────────────────────────────────
     |  QR Payments
     |────────────────────────────────────────────── */

    /**
     * Decode a payment QR payload and resolve the recipient for the current payer.
     *
     * @return array{0: User|null, 1: string|null} [recipient, errorMessage]
     */
    private function resolvePaymentRecipientFromQr(string $qrPayload, User $payer): array
    {
        try {
            $decoded = base64_decode($qrPayload, true);
            $qr = json_decode($decoded);

            if (! $qr || ! isset($qr->t, $qr->uid) || $qr->t !== 'payment') {
                return [null, 'Invalid QR code.'];
            }
        } catch (\Throwable $e) {
            return [null, 'Invalid QR code.'];
        }

        $recipient = User::find($qr->uid);

        if (! $recipient) {
            return [null, 'Invalid recipient.'];
        }

        if ($recipient->id === $payer->id) {
            return [null, 'Invalid recipient.'];
        }

        return [$recipient, null];
    }

    /**
     * Preview recipient details from a scanned payment QR (no money moved).
     *
     * POST /api/wallet/payment-qr-preview
     */
    public function previewPaymentQr(Request $request): JsonResponse
    {
        /** @var User $payer */
        $payer = $request->user();

        $data = $request->validate([
            'qr_payload' => 'required|string',
        ]);

        [$recipient, $error] = $this->resolvePaymentRecipientFromQr($data['qr_payload'], $payer);

        if ($error !== null) {
            return response()->json(['message' => $error], 422);
        }

        return response()->json([
            'recipient' => [
                'id' => $recipient->id,
                'name' => $recipient->name,
                'extracash_number' => $recipient->extracash_number,
                'profile_photo_url' => $recipient->profile_photo_path
                    ? url('/storage/'.ltrim($recipient->profile_photo_path, '/'))
                    : null,
            ],
        ]);
    }

    /**
     * Generate payment QR code for authenticated user.
     *
     * GET /api/qr-code
     */
    public function getUserQrCode(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // QR payload contains user id, name, public identifier, and ExtraCash number
        $payload = json_encode([
            't' => 'payment',
            'uid' => $user->id,
            'name' => $user->name,
            'extracash_number' => $user->extracash_number,
            'iat' => time(),
        ]);

        return response()->json([
            'payload' => base64_encode($payload),
            'user_id' => $user->id,
            'name' => $user->name,
            'extracash_number' => $user->extracash_number,
        ]);
    }

    /**
     * Process a QR payment after scanning another user's code.
     *
     * POST /api/qr-pay
     */
    public function processQrPayment(Request $request): JsonResponse
    {
        /** @var User $payer */
        $payer = $request->user();
        $payerWallet = $payer->wallet;

        if (! $payerWallet) {
            return response()->json(['message' => 'Wallet not found.'], 404);
        }

        $data = $request->validate([
            'qr_payload' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        [$recipient, $qrError] = $this->resolvePaymentRecipientFromQr($data['qr_payload'], $payer);

        if ($qrError !== null) {
            return response()->json(['message' => $qrError], 422);
        }

        $recipientWallet = $recipient->wallet ?? $recipient->wallet()->firstOrCreate(['currency' => 'ZMW']);

        $amount = (float) $data['amount'];

        // Check payer balance
        if ((float) $payerWallet->available_balance < $amount) {
            return response()->json(['message' => 'Insufficient available balance.'], 422);
        }

        $reference = (string) Str::uuid();

        try {
            $transfer = $this->ledger->transfer(
                $payerWallet,
                $recipientWallet,
                $amount,
                User::class,
                $recipient->id,
                $payer->id,
                'transfer_send',
                'transfer_receive',
                ['reference' => $reference, 'to' => $recipient->name],
                ['reference' => $reference, 'from' => $payer->name, 'note' => $data['note'] ?? null]
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->recordRewardActionSafely($payer, 'qr_pay', [
            'source_type' => WalletLedger::class,
            'source_id' => $transfer['debit']->id,
            'reference' => $reference,
        ]);

        Log::info('wallet.qr_pay', [
            'reference' => $reference,
            'amount' => $amount,
            'recipient_user_id' => $recipient->id,
            'debit_ledger_id' => $transfer['debit']->id,
        ]);

        $this->notifications->notifyUser(
            $payer,
            'qr_pay',
            'Payment sent',
            sprintf('You paid K%.2f to %s.', $amount, $recipient->name),
            ['reference' => $reference, 'recipient_user_id' => $recipient->id],
            sendEmail: false,
            sendPush: true,
        );

        $this->notifications->notifyUser(
            $recipient,
            'qr_pay_received',
            'Payment received',
            sprintf('You received K%.2f from %s.', $amount, $payer->name),
            ['reference' => $reference, 'payer_user_id' => $payer->id],
            sendEmail: false,
            sendPush: true,
        );

        return response()->json([
            'message' => 'Payment sent successfully.',
            'reference' => $reference,
            'recipient' => [
                'id' => $recipient->id,
                'name' => $recipient->name,
            ],
        ]);
    }

    /* ──────────────────────────────────────────────
     |  Request Money
     |────────────────────────────────────────────── */

    /**
     * Request money from another user.
     *
     * POST /api/wallet/request-money
     */
    public function requestMoney(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'recipient_email' => 'required|email|exists:users,email',
            'amount' => 'required|numeric|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        $recipient = User::where('email', $data['recipient_email'])->first();

        // Store the request as a ledger entry with direction = 'request_pending'
        // In a full implementation you'd have a dedicated money_requests table.
        // For now we record it in the ledger so it shows in history.
        $ledger = $this->ledger->creditPending(
            $user->wallet ?? $user->wallet()->firstOrCreate(['currency' => 'ZMW', 'available_balance' => 0]),
            'money_request',
            (float) $data['amount'],
            User::class,
            $recipient->id,
            [
                'requester_id' => $user->id,
                'requester_name' => $user->name,
                'recipient_id' => $recipient->id,
                'recipient_name' => $recipient->name,
                'recipient_email' => $recipient->email,
                'note' => $data['note'] ?? null,
                'status' => 'pending',
            ]
        );

        return response()->json([
            'message' => 'Money request sent to '.$recipient->name.'.',
            'request_id' => $ledger->id,
        ]);
    }
}
