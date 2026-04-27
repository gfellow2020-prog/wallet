@extends('admin.layouts.app')
@section('title', 'Fees & revenue — ExtraCash Admin')
@section('page-title', 'Fees & revenue')
@section('breadcrumb', 'Rollup: merchant fees, marketplace admin fees, cashback totals')

@section('content')

<div class="card p-5 mb-4">
    <form method="GET" class="flex gap-2 flex-wrap items-center justify-between">
        <div class="flex gap-2 flex-wrap items-center">
            <input type="date" name="date_from" value="{{ request('date_from', $range['from']->toDateString()) }}"
                   class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
            <input type="date" name="date_to" value="{{ request('date_to', $range['to']->toDateString()) }}"
                   class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
            <button type="submit" class="btn-secondary text-xs py-2">Apply</button>
            <a href="{{ route('admin.finance.revenue.export', request()->query()) }}"
               class="btn-secondary text-xs py-2">Export CSV</a>
        </div>
        <p class="text-xs text-neutral-500">
            {{ $range['from']->format('M d, Y') }} → {{ $range['to']->format('M d, Y') }}
        </p>
    </form>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
    <div class="card p-5">
        <h2 class="font-semibold text-sm text-neutral-900 dark:text-white">Bill / Partner merchant payments</h2>
        <p class="text-xs text-neutral-500 mt-1">Derived from `orders` (gross, fee, net).</p>

        <dl class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-neutral-500">Gross</dt>
                <dd class="font-semibold text-neutral-900 dark:text-white tabular-nums">ZMW {{ number_format($totals['merchant_gross'] ?? 0, 2) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-neutral-500">Fees</dt>
                <dd class="font-semibold text-neutral-900 dark:text-white tabular-nums">ZMW {{ number_format($totals['merchant_fee'] ?? 0, 2) }}</dd>
            </div>
            <div class="flex justify-between gap-4 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                <dt class="text-neutral-500">Net to merchants</dt>
                <dd class="font-semibold text-neutral-900 dark:text-white tabular-nums">ZMW {{ number_format($totals['merchant_net'] ?? 0, 2) }}</dd>
            </div>
        </dl>
    </div>

    <div class="card p-5">
        <h2 class="font-semibold text-sm text-neutral-900 dark:text-white">Marketplace</h2>
        <p class="text-xs text-neutral-500 mt-1">Completed sales only (split: 1% admin fee, 2% cashback, 97% seller net).</p>

        <dl class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-neutral-500">Gross</dt>
                <dd class="font-semibold text-neutral-900 dark:text-white tabular-nums">ZMW {{ number_format($totals['marketplace_gross'] ?? 0, 2) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-neutral-500">Admin fee (revenue)</dt>
                <dd class="font-semibold text-neutral-900 dark:text-white tabular-nums">ZMW {{ number_format($totals['marketplace_admin_fee'] ?? 0, 2) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-neutral-500">Cashback (granted)</dt>
                <dd class="font-semibold text-neutral-900 dark:text-white tabular-nums">ZMW {{ number_format($totals['marketplace_cashback'] ?? 0, 2) }}</dd>
            </div>
            <div class="flex justify-between gap-4 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                <dt class="text-neutral-500">Net to sellers</dt>
                <dd class="font-semibold text-neutral-900 dark:text-white tabular-nums">ZMW {{ number_format($totals['marketplace_seller_net'] ?? 0, 2) }}</dd>
            </div>
        </dl>
    </div>
</div>

<div class="card p-5 mt-4">
    <h2 class="font-semibold text-sm text-neutral-900 dark:text-white">Cashback ledger total</h2>
    <p class="text-xs text-neutral-500 mt-1">Sum of `cashback_transactions.amount` within range (may include sources beyond marketplace).</p>
    <p class="mt-2 text-2xl font-bold text-neutral-900 dark:text-white">ZMW {{ number_format($totals['cashback_ledger_total'] ?? 0, 2) }}</p>
</div>

@endsection

