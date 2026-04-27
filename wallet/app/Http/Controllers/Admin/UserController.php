<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserAdminNote;
use App\Models\UserOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['wallet', 'kycRecords'])
            ->when(request('q'), fn ($q, $search) => $q->where('name', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%")
            )
            ->latest()
            ->paginate(25);

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $user->load([
            'wallet.transactions' => fn ($q) => $q->latest('transacted_at')->limit(12),
            'kycRecords' => fn ($q) => $q->latest()->with('reviewer'),
            'payments' => fn ($q) => $q->latest()->with('cashback')->limit(12),
            'withdrawals' => fn ($q) => $q->latest()->limit(12),
            'cashbackTransactions' => fn ($q) => $q->latest()->with('payment')->limit(12),
            'adminNotes' => fn ($q) => $q->latest()->with('admin')->limit(20),
            'roles',
            'suspendedBy:id,name,email',
        ]);

        $stats = [
            'payments_count' => $user->payments()->count(),
            'payments_total' => (float) $user->payments()->sum('amount'),
            'withdrawals_count' => $user->withdrawals()->count(),
            'withdrawals_total' => (float) $user->withdrawals()->sum('amount'),
            'cashbacks_count' => $user->cashbackTransactions()->count(),
            'cashbacks_total' => (float) $user->cashbackTransactions()->sum('cashback_amount'),
        ];

        $allRoles = \Spatie\Permission\Models\Role::query()->orderBy('name')->get();

        return view('admin.users.show', compact('user', 'stats', 'allRoles'));
    }

    protected function audit(Request $request, string $action, ?User $subject = null, array $old = [], array $new = []): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->id,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
        ]);

        $old = $user->only(['suspended_at', 'suspended_by', 'suspension_reason']);

        $user->update([
            'suspended_at' => now(),
            'suspended_by' => $request->user()?->id,
            'suspension_reason' => $data['reason'],
        ]);

        $this->audit($request, 'user.suspend', $user, $old, $user->only(['suspended_at', 'suspended_by', 'suspension_reason']));

        return redirect()->route('admin.users.show', $user)->with('success', 'User suspended.');
    }

    public function unsuspend(Request $request, User $user): RedirectResponse
    {
        $old = $user->only(['suspended_at', 'suspended_by', 'suspension_reason']);

        $user->update([
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ]);

        $this->audit($request, 'user.unsuspend', $user, $old, $user->only(['suspended_at', 'suspended_by', 'suspension_reason']));

        return redirect()->route('admin.users.show', $user)->with('success', 'User unsuspended.');
    }

    public function updateProfile(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email,'.$user->id],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'nrc_number' => ['nullable', 'string', 'max:30'],
            'tpin' => ['nullable', 'string', 'max:30'],
            'phone_verified' => ['nullable', 'boolean'],
        ]);

        $old = $user->only(['name', 'email', 'phone_number', 'nrc_number', 'tpin', 'phone_verified_at']);

        $phoneVerifiedAt = $request->boolean('phone_verified')
            ? ($user->phone_verified_at ?? now())
            : null;

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'nrc_number' => $data['nrc_number'] ?? null,
            'tpin' => $data['tpin'] ?? null,
            'phone_verified_at' => $phoneVerifiedAt,
        ]);

        $this->audit($request, 'user.update_profile', $user, $old, $user->only(['name', 'email', 'phone_number', 'nrc_number', 'tpin', 'phone_verified_at']));

        return redirect()->route('admin.users.show', $user)->with('success', 'User profile updated.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        // Generate a temporary password and show it once via flash.
        $temp = 'EC-'.Str::upper(Str::random(10));

        $old = ['password' => '***'];
        $user->update(['password' => Hash::make($temp)]);

        $this->audit($request, 'user.reset_password', $user, $old, ['password' => '***']);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'Temporary password generated. Copy it now — it will not be shown again.')
            ->with('temp_password', $temp);
    }

    public function forceLogout(Request $request, User $user): RedirectResponse
    {
        // Web sessions (database driver)
        DB::table('sessions')->where('user_id', $user->id)->delete();

        // Sanctum tokens
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        $this->audit($request, 'user.force_logout', $user, [], ['sessions_deleted' => true, 'tokens_deleted' => true]);

        return redirect()->route('admin.users.show', $user)->with('success', 'User sessions revoked (web + API).');
    }

    public function resetOtpLockouts(Request $request, User $user): RedirectResponse
    {
        $updated = UserOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->whereNull('revoked_at')
            ->update([
                'attempts' => 0,
                'revoked_at' => now(),
            ]);

        $this->audit($request, 'user.reset_otp_lockouts', $user, [], ['revoked_challenges' => $updated]);

        return redirect()->route('admin.users.show', $user)->with('success', 'OTP challenges reset.');
    }

    public function addNote(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
        ]);

        $note = UserAdminNote::create([
            'user_id' => $user->id,
            'admin_id' => (int) ($request->user()?->id ?? 0),
            'note' => $data['note'],
        ]);

        $this->audit($request, 'user.add_note', $user, [], ['note_id' => $note->id]);

        return redirect()->route('admin.users.show', $user)->with('success', 'Note added.');
    }

    public function fundWallet(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:100000000',
            'narration' => 'nullable|string|max:120',
        ]);

        $wallet = $user->wallet;
        if (! $wallet) {
            $wallet = $user->wallet()->create([
                'available_balance' => 0,
                'pending_balance' => 0,
                'currency' => 'ZMW',
            ]);
        }

        $amount = round((float) $data['amount'], 2);

        $wallet->increment('available_balance', $amount);

        $wallet->transactions()->create([
            'type' => 'credit',
            'amount' => $amount,
            'narration' => $data['narration'] ?: 'Admin manual wallet funding',
            'gateway_status' => 'admin_manual',
            'transacted_at' => now(),
        ]);

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Wallet funded successfully: ZMW '.number_format($amount, 2));
    }

    public function syncRoles(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $roles = $data['roles'] ?? [];

        if (! $request->user() || ! method_exists($request->user(), 'hasRole') || ! $request->user()->hasRole('super_admin')) {
            $roles = array_values(array_filter($roles, fn (string $r) => $r !== 'super_admin'));
        }

        $user->syncRoles($roles);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'User roles updated.');
    }
}
