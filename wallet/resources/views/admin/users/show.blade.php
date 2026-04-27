@extends('admin.layouts.app')
@section('title', 'User Detail — ExtraCash Admin')
@section('page-title', 'User Detail')
@section('breadcrumb', 'Account overview')

@section('content')

@php
    $latestKyc = $user->kycRecords->first();
    $badgeClass = function (?string $status) {
        return match ($status) {
            'verified', 'successful' => 'verified',
            'pending', 'processing', 'requested', 'under_review', 'locked' => 'pending',
            'approved', 'paid', 'available' => 'approved',
            'rejected', 'failed', 'reversed', 'cancelled', 'expired', 'not_submitted' => 'rejected',
            default => 'pending',
        };
    };
    $labelize = fn (?string $s) => $s ? ucwords(str_replace('_', ' ', $s)) : '—';
@endphp

<div class="mb-5 flex items-center justify-between gap-3">
    <a href="{{ route('admin.users') }}" class="btn-secondary text-xs">← Back to users</a>
    <span class="text-xs text-neutral-500">User ID: {{ $user->id }}</span>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="card p-4">
        <p class="text-xs uppercase tracking-wider text-neutral-500 font-semibold">Wallet Total</p>
        <p class="text-2xl font-bold mt-1 text-neutral-900 dark:text-white">
            ZMW {{ number_format(($user->wallet?->available_balance ?? 0) + ($user->wallet?->pending_balance ?? 0), 2) }}
        </p>
    </div>
    <div class="card p-4">
        <p class="text-xs uppercase tracking-wider text-neutral-500 font-semibold">Payments</p>
        <p class="text-2xl font-bold mt-1 text-neutral-900 dark:text-white">{{ number_format($stats['payments_count']) }}</p>
        <p class="text-xs text-neutral-500 mt-1">ZMW {{ number_format($stats['payments_total'], 2) }} total</p>
    </div>
    <div class="card p-4">
        <p class="text-xs uppercase tracking-wider text-neutral-500 font-semibold">Withdrawals</p>
        <p class="text-2xl font-bold mt-1 text-neutral-900 dark:text-white">{{ number_format($stats['withdrawals_count']) }}</p>
        <p class="text-xs text-neutral-500 mt-1">ZMW {{ number_format($stats['withdrawals_total'], 2) }} total</p>
    </div>
    <div class="card p-4">
        <p class="text-xs uppercase tracking-wider text-neutral-500 font-semibold">Cashback Earned</p>
        <p class="text-2xl font-bold mt-1 text-neutral-900 dark:text-white">{{ number_format($stats['cashbacks_count']) }}</p>
        <p class="text-xs text-neutral-500 mt-1">ZMW {{ number_format($stats['cashbacks_total'], 2) }} total</p>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    {{-- Profile + wallet tools --}}
    <div class="space-y-6">
        <div class="card p-6">
            <div class="flex items-center gap-4 mb-5">
                <div class="w-12 h-12 rounded bg-neutral-200 dark:bg-neutral-700 flex items-center justify-center text-lg font-bold text-neutral-700 dark:text-neutral-200">
                    {{ strtoupper(substr($user->name, 0, 2)) }}
                </div>
                <div>
                    <p class="font-bold text-neutral-900 dark:text-white">{{ $user->name }}</p>
                    <p class="text-sm text-neutral-500 break-all">{{ $user->email }}</p>
                </div>
            </div>

            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-neutral-500">Joined</dt>
                    <dd class="font-medium text-neutral-900 dark:text-white">{{ $user->created_at->format('M d, Y H:i') }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-neutral-500">KYC Status</dt>
                    <dd>
                        <span class="badge badge-{{ $badgeClass($latestKyc?->status?->value) }}">
                            {{ $labelize($latestKyc?->status?->value) }}
                        </span>
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-neutral-500">Account Status</dt>
                    <dd>
                        @if($user->suspended_at)
                            <span class="badge badge-rejected">Suspended</span>
                        @else
                            <span class="badge badge-approved">Active</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-neutral-500">Available Balance</dt>
                    <dd class="font-semibold text-neutral-900 dark:text-white">ZMW {{ number_format($user->wallet?->available_balance ?? 0, 2) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-neutral-500">Pending Balance</dt>
                    <dd class="text-neutral-600 dark:text-neutral-300">ZMW {{ number_format($user->wallet?->pending_balance ?? 0, 2) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-neutral-500">Lifetime Cashback</dt>
                    <dd class="text-neutral-600 dark:text-neutral-300">ZMW {{ number_format($user->wallet?->lifetime_cashback_earned ?? 0, 2) }}</dd>
                </div>
            </dl>
        </div>

        <div class="card p-6">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500 mb-3">Account actions</h4>

            @if($user->suspended_at)
                <div class="mb-3 text-xs text-neutral-500">
                    Suspended {{ $user->suspended_at?->format('M d, Y H:i') }} by {{ $user->suspendedBy?->name ?? '—' }}.
                </div>
                @if($user->suspension_reason)
                    <div class="mb-4 text-xs text-neutral-600 dark:text-neutral-300">
                        <span class="font-semibold text-neutral-500">Reason:</span> {{ $user->suspension_reason }}
                    </div>
                @endif
                <form method="POST" action="{{ route('admin.users.unsuspend', $user) }}">
                    @csrf
                    <button type="submit" class="btn-primary w-full justify-center">Unsuspend user</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-neutral-600 mb-1">Suspension reason</label>
                        <textarea name="reason" rows="3" maxlength="5000"
                                  class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white"
                                  placeholder="Why is this user being suspended?" required>{{ old('reason') }}</textarea>
                        @error('reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn-danger w-full justify-center">Suspend user</button>
                </form>
            @endif

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                <form method="POST" action="{{ route('admin.users.force-logout', $user) }}">
                    @csrf
                    <button type="submit" class="btn-secondary w-full justify-center" onclick="return confirm('Force logout this user from all devices?')">
                        Force logout
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.users.otp-reset', $user) }}">
                    @csrf
                    <button type="submit" class="btn-secondary w-full justify-center" onclick="return confirm('Reset OTP challenges/lockouts for this user?')">
                        Reset OTP
                    </button>
                </form>
            </div>

            <form method="POST" action="{{ route('admin.users.password.reset', $user) }}" class="mt-3">
                @csrf
                <button type="submit" class="btn-secondary w-full justify-center" onclick="return confirm('Generate a temporary password for this user?')">
                    Reset password
                </button>
            </form>

            @if(session('temp_password'))
                <div class="mt-3 p-3 rounded border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900">
                    <p class="text-xs text-neutral-500 mb-1">Temporary password (copy now):</p>
                    <p class="font-mono text-sm font-semibold text-neutral-900 dark:text-white break-all">{{ session('temp_password') }}</p>
                </div>
            @endif
        </div>

        <div class="card p-6">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500 mb-3">Edit profile</h4>
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-neutral-600 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" maxlength="120"
                           class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white" required />
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-600 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="190"
                           class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white" required />
                    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-neutral-600 mb-1">Phone</label>
                        <input type="text" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}" maxlength="20"
                               class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white" />
                        @error('phone_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex items-center gap-2 pt-6">
                        <input id="phone_verified" type="checkbox" name="phone_verified" value="1"
                               class="rounded border-neutral-300"
                               {{ old('phone_verified', $user->phone_verified_at ? 1 : 0) ? 'checked' : '' }} />
                        <label for="phone_verified" class="text-sm text-neutral-700 dark:text-neutral-300">Phone verified</label>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-neutral-600 mb-1">NRC</label>
                        <input type="text" name="nrc_number" value="{{ old('nrc_number', $user->nrc_number) }}" maxlength="30"
                               class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white" />
                        @error('nrc_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-neutral-600 mb-1">TPIN</label>
                        <input type="text" name="tpin" value="{{ old('tpin', $user->tpin) }}" maxlength="30"
                               class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white" />
                        @error('tpin')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <button type="submit" class="btn-primary w-full justify-center">Save profile</button>
            </form>
        </div>

        <div class="card p-6">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500 mb-3">Internal notes</h4>
            <form method="POST" action="{{ route('admin.users.notes', $user) }}" class="space-y-3">
                @csrf
                <textarea name="note" rows="3" maxlength="5000"
                          class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white"
                          placeholder="Add a note for other admins…" required>{{ old('note') }}</textarea>
                @error('note')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                <button type="submit" class="btn-secondary w-full justify-center">Add note</button>
            </form>

            <div class="mt-4 space-y-3">
                @forelse($user->adminNotes ?? [] as $n)
                    <div class="p-3 rounded border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-950">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs text-neutral-500">{{ $n->admin?->name ?? 'Admin' }}</p>
                            <p class="text-xs text-neutral-500">{{ $n->created_at?->format('M d, Y H:i') }}</p>
                        </div>
                        <p class="mt-2 text-sm text-neutral-800 dark:text-neutral-200 whitespace-pre-wrap">{{ $n->note }}</p>
                    </div>
                @empty
                    <p class="text-sm text-neutral-400">No notes yet</p>
                @endforelse
            </div>
        </div>

        @can('users.assign_roles')
            <div class="card p-6">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Roles</h4>
                    <div class="text-xs text-neutral-500">
                        {{ $user->roles->pluck('name')->implode(', ') ?: 'No roles' }}
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.users.roles.sync', $user) }}" class="space-y-3">
                    @csrf

                    <div>
                        <label class="block text-xs font-semibold text-neutral-600 mb-1">Assign roles</label>
                        <select name="roles[]" multiple
                                class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white min-h-[120px]">
                            @foreach($allRoles as $r)
                                <option value="{{ $r->name }}" @selected($user->roles->contains('name', $r->name))>
                                    {{ $r->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('roles')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        @error('roles.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        <p class="mt-2 text-xs text-neutral-500">Hold Ctrl/Command to select multiple roles.</p>
                    </div>

                    <button type="submit" class="btn-primary w-full justify-center">Save roles</button>
                </form>
            </div>
        @endcan

        <div class="card p-6">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500 mb-3">Manual Wallet Funding</h4>

            <form method="POST" action="{{ route('admin.users.fund-wallet', $user) }}" class="space-y-3">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-neutral-600 mb-1">Amount (ZMW)</label>
                    <input
                        type="number"
                        name="amount"
                        value="{{ old('amount') }}"
                        step="0.01"
                        min="0.01"
                        placeholder="e.g. 250.00"
                        class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white"
                        required
                    />
                    @error('amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-neutral-600 mb-1">Narration (optional)</label>
                    <input
                        type="text"
                        name="narration"
                        value="{{ old('narration') }}"
                        maxlength="120"
                        placeholder="Admin adjustment / Top-up"
                        class="w-full px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white"
                    />
                    @error('narration')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="btn-primary w-full justify-center">Add Funds</button>
            </form>
        </div>

        <div class="card overflow-hidden">
            <div class="px-5 py-3 border-b border-neutral-200 dark:border-neutral-800">
                <h3 class="font-semibold text-sm text-neutral-900 dark:text-white">KYC History</h3>
            </div>
            <div class="divide-y divide-neutral-50 dark:divide-neutral-800">
                @forelse($user->kycRecords->take(8) as $k)
                    <div class="px-5 py-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-neutral-800 dark:text-neutral-100">{{ strtoupper($k->id_type ?? 'ID') }} · {{ $k->id_number ?? '—' }}</p>
                            <span class="badge badge-{{ $badgeClass($k->status?->value) }}">{{ $labelize($k->status?->value) }}</span>
                        </div>
                        <p class="text-xs text-neutral-500 mt-1">Submitted {{ $k->created_at?->format('M d, Y H:i') ?? '—' }}</p>
                        @if($k->review_notes)
                            <p class="text-xs text-neutral-600 mt-1">{{ $k->review_notes }}</p>
                        @endif
                    </div>
                @empty
                    <p class="px-5 py-4 text-sm text-neutral-400">No KYC records</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Activity tables --}}
    <div class="xl:col-span-2 space-y-6">
        <div class="card overflow-hidden">
            <div class="px-5 py-3 border-b border-neutral-200 dark:border-neutral-800">
                <h3 class="font-semibold text-sm text-neutral-900 dark:text-white">Recent Payments</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100 dark:border-neutral-800">
                            <th class="px-5 py-3 text-left">Reference</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3 text-center">Status</th>
                            <th class="px-5 py-3 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($user->payments as $p)
                            <tr class="border-b border-neutral-50 dark:border-neutral-800/50">
                                <td class="px-5 py-3 text-neutral-700 dark:text-neutral-300">{{ $p->payment_reference ?? ('#'.$p->id) }}</td>
                                <td class="px-5 py-3 text-right font-medium">ZMW {{ number_format($p->amount, 2) }}</td>
                                <td class="px-5 py-3 text-center"><span class="badge badge-{{ $badgeClass($p->status?->value) }}">{{ $labelize($p->status?->value) }}</span></td>
                                <td class="px-5 py-3 text-right text-xs text-neutral-500">{{ $p->created_at?->format('M d, Y H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-6 text-center text-neutral-400">No payments</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card overflow-hidden">
                <div class="px-5 py-3 border-b border-neutral-200 dark:border-neutral-800">
                    <h3 class="font-semibold text-sm text-neutral-900 dark:text-white">Withdrawals</h3>
                </div>
                <div class="divide-y divide-neutral-50 dark:divide-neutral-800">
                    @forelse($user->withdrawals as $w)
                        <div class="px-5 py-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ $w->reference ?? ('Withdrawal #'.$w->id) }}</p>
                                <p class="text-xs text-neutral-500">{{ $w->created_at?->format('M d, Y H:i') ?? '—' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-medium">ZMW {{ number_format($w->amount, 2) }}</p>
                                <span class="badge badge-{{ $badgeClass($w->status?->value) }}">{{ $labelize($w->status?->value) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-4 text-sm text-neutral-400">No withdrawals</p>
                    @endforelse
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="px-5 py-3 border-b border-neutral-200 dark:border-neutral-800">
                    <h3 class="font-semibold text-sm text-neutral-900 dark:text-white">Cashback Transactions</h3>
                </div>
                <div class="divide-y divide-neutral-50 dark:divide-neutral-800">
                    @forelse($user->cashbackTransactions as $cb)
                        <div class="px-5 py-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-neutral-800 dark:text-neutral-200">Payment: {{ $cb->payment?->payment_reference ?? ('#'.$cb->payment_id) }}</p>
                                <p class="text-xs text-neutral-500">Rate {{ number_format(((float)$cb->cashback_rate) * 100, 2) }}% • {{ $cb->created_at?->format('M d, Y H:i') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-medium">ZMW {{ number_format($cb->cashback_amount, 2) }}</p>
                                <span class="badge badge-{{ $badgeClass($cb->status?->value) }}">{{ $labelize($cb->status?->value) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-4 text-sm text-neutral-400">No cashback transactions</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="px-5 py-3 border-b border-neutral-200 dark:border-neutral-800">
                <h3 class="font-semibold text-sm text-neutral-900 dark:text-white">Recent Wallet Transactions</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100 dark:border-neutral-800">
                            <th class="px-5 py-3 text-left">Narration</th>
                            <th class="px-5 py-3 text-center">Type</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($user->wallet?->transactions ?? [] as $t)
                            <tr class="border-b border-neutral-50 dark:border-neutral-800/50">
                                <td class="px-5 py-3 text-neutral-700 dark:text-neutral-300">{{ $t->narration }}</td>
                                <td class="px-5 py-3 text-center">
                                    <span class="badge badge-{{ $t->type === 'credit' ? 'approved' : 'rejected' }}">{{ ucfirst($t->type) }}</span>
                                </td>
                                <td class="px-5 py-3 text-right font-medium {{ $t->type === 'credit' ? 'text-green-700 dark:text-green-400' : 'text-neutral-800 dark:text-neutral-200' }}">
                                    {{ $t->type === 'credit' ? '+' : '-' }}ZMW {{ number_format($t->amount, 2) }}
                                </td>
                                <td class="px-5 py-3 text-right text-xs text-neutral-500">{{ $t->transacted_at?->format('M d, Y H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-6 text-center text-neutral-400">No wallet transactions</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
