<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ExtraCashGatewayService;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WalletController extends Controller
{
    public function __construct(
        protected ExtraCashGatewayService $extracashGateway,
        protected NotificationService $notifications,
    ) {}

    private function getUser(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        return User::query()
            ->with(['wallet.transactions'])
            ->find($user->id);
    }

    private function currencySymbol(string $code): string
    {
        return match ($code) {
            'ZMW' => 'K',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $code,
        };
    }

    private function cardData(?Wallet $wallet): array
    {
        $currencyCode = $wallet?->currency ?? 'ZMW';
        $seed = (string) ($wallet?->id ?? 1);

        return [
            'currencyCode' => $currencyCode,
            'currencySymbol' => $this->currencySymbol($currencyCode),
            'maskedCardNumber' => '**** **** **** '.str_pad(substr($seed, -4), 4, '0', STR_PAD_LEFT),
            'expiry' => now()->addYears(4)->format('m/y'),
        ];
    }

    private function mapGatewayStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'success', 'successful', 'completed' => 'success',
            'failed', 'reversed', 'declined' => 'failed',
            default => 'pending',
        };
    }

    /**
     * Apply a gateway status change exactly once.
     *
     * Deposits credit the wallet the first time they enter `success`.
     * Disbursements refund the wallet the first time they enter `failed`.
     */
    private function settleGatewayTransaction(Transaction $transaction, string $mappedStatus): Transaction
    {
        return DB::transaction(function () use ($transaction, $mappedStatus) {
            /** @var Transaction $locked */
            $locked = Transaction::query()
                ->with('wallet')
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            $previousStatus = (string) ($locked->gateway_status ?? 'pending');

            if ($previousStatus === $mappedStatus) {
                return $locked;
            }

            $locked->update(['gateway_status' => $mappedStatus]);

            if ($locked->type === 'credit' && $previousStatus !== 'success' && $mappedStatus === 'success') {
                $this->incrementWalletBalance($locked->wallet, (float) $locked->amount);
            }

            if ($locked->type === 'debit' && $previousStatus !== 'failed' && $mappedStatus === 'failed') {
                $this->incrementWalletBalance($locked->wallet, (float) $locked->amount);
            }

            return $locked->fresh(['wallet']);
        });
    }

    private function incrementWalletBalance(Wallet $wallet, float $amount): void
    {
        $lockedWallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);

        if (Schema::hasColumn('wallets', 'available_balance')) {
            $lockedWallet->increment('available_balance', $amount);

            return;
        }

        if (Schema::hasColumn('wallets', 'balance')) {
            $lockedWallet->increment('balance', $amount);
        }
    }

    /* ── Dashboard ────────────────────────────────── */

    public function index(): View
    {
        $user = $this->getUser();
        $wallet = $user?->wallet;

        return view('wallet.index', [
            'user' => $user,
            'wallet' => $wallet,
            'transactions' => $wallet?->transactions ?? new Collection,
            ...$this->cardData($wallet),
        ]);
    }

    /* ── Fund Wallet (Collection) ─────────────────── */

    public function fundForm(): View
    {
        return view('wallet.fund');
    }

    public function fund(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'phone_number' => 'required|string|regex:/^260[0-9]{9}$/',
            'narration' => 'nullable|string|max:120',
        ], [
            'phone_number.regex' => 'Enter a valid Zambian number starting with 260 (e.g. 260971234567).',
        ]);

        $wallet = $this->getUser()?->wallet;
        abort_unless($wallet, 404);

        // Call mobile-money gateway collection API
        $result = $this->extracashGateway->collect($data['phone_number'], (float) $data['amount']);

        // Create a pending transaction regardless of outcome
        $transaction = $wallet->transactions()->create([
            'type' => 'credit',
            'amount' => $data['amount'],
            'narration' => $data['narration'] ?? 'Mobile money deposit',
            'phone_number' => $data['phone_number'],
            'gateway_reference' => $result['reference'] ?? null,
            'gateway_status' => $result['success'] ? 'pending' : 'failed',
            'transacted_at' => now(),
        ]);

        if ($result['success']) {
            return redirect()->route('wallet.index')
                ->with('success', 'Collection request sent to '.$data['phone_number'].'. Approve the prompt on your phone to fund K'.number_format((float) $data['amount'], 2).'.');
        }

        return redirect()->route('wallet.fund')
            ->withInput()
            ->withErrors(['amount' => 'Payment gateway error: '.($result['message'] ?? 'Unknown error')]);
    }

    /* ── Send Money (Disbursement) ────────────────── */

    public function sendForm(): View
    {
        $wallet = $this->getUser()?->wallet;

        return view('wallet.send', ['balance' => $wallet?->balance ?? 0]);
    }

    public function send(Request $request): RedirectResponse
    {
        $wallet = $this->getUser()?->wallet;
        abort_unless($wallet, 404);

        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:'.$wallet->balance,
            'phone_number' => 'required|string|regex:/^260[0-9]{9}$/',
            'recipient' => 'required|string|max:120',
        ], [
            'phone_number.regex' => 'Enter a valid Zambian number starting with 260 (e.g. 260971234567).',
        ]);

        // Call mobile-money gateway disbursement API
        $narration = 'Sent to '.$data['recipient'];
        $result = $this->extracashGateway->disburse($data['phone_number'], (float) $data['amount'], $narration);

        if ($result['success']) {
            // Debit the wallet immediately
            $wallet->decrement('balance', $data['amount']);

            $wallet->transactions()->create([
                'type' => 'debit',
                'amount' => $data['amount'],
                'narration' => $narration,
                'phone_number' => $data['phone_number'],
                'gateway_reference' => $result['reference'] ?? null,
                'gateway_status' => 'pending',
                'transacted_at' => now(),
            ]);

            return redirect()->route('wallet.index')
                ->with('success', 'K'.number_format((float) $data['amount'], 2).' sent to '.$data['recipient'].' ('.$data['phone_number'].').');
        }

        // Gateway rejected — don't debit
        return redirect()->route('wallet.send')
            ->withInput()
            ->withErrors(['amount' => 'Disbursement failed: '.($result['message'] ?? 'Unknown error')]);
    }

    /* ── Pay Bills ────────────────────────────────── */

    public function payForm(): View
    {
        $wallet = $this->getUser()?->wallet;

        return view('wallet.pay', ['balance' => $wallet?->balance ?? 0]);
    }

    public function pay(Request $request): RedirectResponse
    {
        $wallet = $this->getUser()?->wallet;
        abort_unless($wallet, 404);

        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:'.$wallet->balance,
            'biller' => 'required|string|max:120',
        ]);

        $wallet->decrement('balance', $data['amount']);
        $wallet->transactions()->create([
            'type' => 'debit',
            'amount' => $data['amount'],
            'narration' => 'Paid '.$data['biller'],
            'gateway_status' => 'local',
            'transacted_at' => now(),
        ]);

        return redirect()->route('wallet.index')
            ->with('success', 'K'.number_format((float) $data['amount'], 2).' paid to '.$data['biller'].'!');
    }

    /* ── History ──────────────────────────────────── */

    public function history(): View
    {
        $user = $this->getUser();
        $wallet = $user?->wallet;
        $transactions = $wallet?->transactions ?? new Collection;

        return view('wallet.history', [
            'transactions' => $transactions,
        ]);
    }

    public function profile(): View
    {
        $user = $this->getUser();
        $wallet = $user?->wallet;

        return view('wallet.profile', [
            'user' => $user,
            'wallet' => $wallet,
        ]);
    }

    /* ── ExtraCash / GeePay gateway webhook ─────────── */

    public function extracashGatewayCallback(Request $request): JsonResponse
    {
        if (! $this->extracashGateway->hasWebhookVerificationConfigured()) {
            return response()->json(['message' => 'Mobile-money gateway webhook secret is not configured.'], 503);
        }

        $providedSecret = $request->header($this->extracashGateway->webhookHeader());

        if (! $this->extracashGateway->verifyWebhookHeader($providedSecret)) {
            $this->notifications->notifyAdmins(
                'admin_webhook_invalid',
                'ExtraCash webhook rejected',
                'Invalid webhook secret on /webhook/extracash (or /webhook/geepay).',
                ['ip' => $request->ip()],
                sendEmail: true,
            );
            return response()->json(['message' => 'Invalid webhook signature.'], 403);
        }

        $payload = $request->all();
        $reference = $payload['reference'] ?? $payload['transaction_ref'] ?? null;
        $status = $payload['status'] ?? null;

        if (! $reference) {
            return response()->json(['message' => 'No reference provided'], 400);
        }

        $transaction = Transaction::where('gateway_reference', $reference)->first();

        if (! $transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $this->settleGatewayTransaction($transaction, $this->mapGatewayStatus($status));

        return response()->json(['message' => 'Callback processed']);
    }

    /* ── Check transaction status (AJAX) ──────────── */

    public function checkStatus(Transaction $transaction): JsonResponse
    {
        if (! $transaction->gateway_reference) {
            return response()->json(['status' => $transaction->gateway_status]);
        }

        // Determine which status endpoint to call
        $result = $transaction->type === 'credit'
            ? $this->extracashGateway->collectStatus($transaction->gateway_reference)
            : $this->extracashGateway->disburseStatus($transaction->gateway_reference);

        $gatewayStatus = $result['data']['status'] ?? null;

        if ($gatewayStatus) {
            $transaction = $this->settleGatewayTransaction($transaction, $this->mapGatewayStatus($gatewayStatus));
        }

        return response()->json([
            'status' => $transaction->gateway_status,
            'details' => $result['data'] ?? [],
        ]);
    }

    /* ── Mobile-money gateway balance ─────────────── */

    public function gatewayBalance(): JsonResponse
    {
        $disburse = $this->extracashGateway->disburseBalance();
        $collect = $this->extracashGateway->collectBalance();

        return response()->json([
            'disburse_balance' => $disburse['data'] ?? null,
            'collect_balance' => $collect['data'] ?? null,
        ]);
    }

    /* ── Name Lookup (AJAX) ───────────────────────── */

    public function nameLookup(Request $request): JsonResponse
    {
        $request->validate(['phone_number' => 'required|string|regex:/^260[0-9]{9}$/']);

        $result = $this->extracashGateway->nameLookup($request->input('phone_number'));

        return response()->json($result);
    }
}
