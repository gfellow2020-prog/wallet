@extends('admin.layouts.app')
@section('title', 'Reconciliation — ExtraCash Admin')
@section('page-title', 'Reconciliation')
@section('breadcrumb', 'Find issues that need finance attention')

@section('content')

<div class="card p-5 mb-4">
    <form method="GET" class="flex gap-2 flex-wrap items-center justify-between">
        <div class="flex gap-2 flex-wrap items-center">
            <input type="date" name="date_from" value="{{ request('date_from', $range['from']->toDateString()) }}"
                   class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
            <input type="date" name="date_to" value="{{ request('date_to', $range['to']->toDateString()) }}"
                   class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
            <input type="number" min="1" name="stuck_hours" value="{{ request('stuck_hours', $stuckHours) }}"
                   class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white w-32"
                   placeholder="Stuck (hrs)">
            <button type="submit" class="btn-secondary text-xs py-2">Apply</button>
            <a href="{{ route('admin.finance.reconciliation.export', request()->query()) }}"
               class="btn-secondary text-xs py-2">Export CSV</a>
        </div>
        <p class="text-xs text-neutral-500">
            Date range: {{ $range['from']->format('M d, Y') }} → {{ $range['to']->format('M d, Y') }}
        </p>
    </form>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-sm text-neutral-900 dark:text-white">Payments missing provider reference</h2>
                <p class="text-xs text-neutral-500 mt-0.5">Usually means gateway callback didn’t store provider ref.</p>
            </div>
            <span class="text-xs text-neutral-500">{{ $paymentsMissingProviderRef->total() }} total</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-100 dark:border-neutral-800">
                        <th class="px-5 py-3 text-left">Payment ref</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3 text-right">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($paymentsMissingProviderRef as $p)
                    <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                        <td class="px-5 py-3 font-mono text-xs text-neutral-600 dark:text-neutral-400">{{ $p->payment_reference ?? '—' }}</td>
                        <td class="px-5 py-3 text-right text-neutral-900 dark:text-white tabular-nums">ZMW {{ number_format($p->amount ?? 0, 2) }}</td>
                        <td class="px-5 py-3 text-right text-xs text-neutral-500">{{ $p->created_at?->format('M d, H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-5 py-8 text-center text-neutral-400">No issues</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($paymentsMissingProviderRef->hasPages())
        <div class="px-5 py-4 border-t border-neutral-100 dark:border-neutral-800">
            {{ $paymentsMissingProviderRef->withQueryString()->links() }}
        </div>
        @endif
    </div>

    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-sm text-neutral-900 dark:text-white">Withdrawals stuck</h2>
                <p class="text-xs text-neutral-500 mt-0.5">Requested/UnderReview/Processing older than {{ $stuckHours }}h.</p>
            </div>
            <span class="text-xs text-neutral-500">{{ $withdrawalsStuck->total() }} total</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-100 dark:border-neutral-800">
                        <th class="px-5 py-3 text-left">User</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-right">Requested</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($withdrawalsStuck as $w)
                    <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                        <td class="px-5 py-3 text-neutral-800 dark:text-neutral-200">{{ $w->user?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-right text-neutral-900 dark:text-white tabular-nums">ZMW {{ number_format($w->amount ?? 0, 2) }}</td>
                        <td class="px-5 py-3 text-center">
                            <span class="badge badge-{{ strtolower($w->status?->value ?? $w->status ?? 'requested') }}">
                                {{ $w->status?->value ?? $w->status ?? '—' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right text-xs text-neutral-500">{{ $w->created_at?->format('M d, H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-neutral-400">No issues</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($withdrawalsStuck->hasPages())
        <div class="px-5 py-4 border-t border-neutral-100 dark:border-neutral-800">
            {{ $withdrawalsStuck->withQueryString()->links() }}
        </div>
        @endif
    </div>

    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-sm text-neutral-900 dark:text-white">Gateway transaction issues</h2>
                <p class="text-xs text-neutral-500 mt-0.5">Wallet transactions with non-success gateway status.</p>
            </div>
            <span class="text-xs text-neutral-500">{{ $gatewayTxIssues->total() }} total</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-100 dark:border-neutral-800">
                        <th class="px-5 py-3 text-left">Gateway ref</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3 text-right">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($gatewayTxIssues as $t)
                    <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                        <td class="px-5 py-3 font-mono text-xs text-neutral-600 dark:text-neutral-400">{{ $t->gateway_reference ?? '—' }}</td>
                        <td class="px-5 py-3 text-center">
                            <span class="badge badge-{{ strtolower($t->gateway_status ?? 'pending') }}">
                                {{ $t->gateway_status ?? '—' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right text-neutral-900 dark:text-white tabular-nums">ZMW {{ number_format($t->amount ?? 0, 2) }}</td>
                        <td class="px-5 py-3 text-right text-xs text-neutral-500">{{ $t->transacted_at?->format('M d, H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-neutral-400">No issues</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($gatewayTxIssues->hasPages())
        <div class="px-5 py-4 border-t border-neutral-100 dark:border-neutral-800">
            {{ $gatewayTxIssues->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>

@endsection

