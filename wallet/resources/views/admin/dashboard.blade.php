@extends('admin.layouts.app')
@section('title', 'Dashboard — ExtraCash Admin')
@section('page-title', 'Dashboard')
@section('breadcrumb', 'Overview of the platform')

@section('content')

{{-- Primary KPIs (4) --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
    @php
        $overviewCards = [
            ['label' => 'Total users', 'value' => number_format($stats['users']), 'meta' => 'Registered accounts', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['label' => 'Payments today', 'value' => 'ZMW ' . number_format($stats['payments_today'], 2), 'meta' => $stats['payments_today_count'] . ' transaction(s)', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
            ['label' => 'Pending withdrawals', 'value' => number_format($stats['withdrawals_pending']), 'meta' => 'Awaiting review', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
            ['label' => 'KYC pending', 'value' => number_format($stats['kyc_pending']), 'meta' => 'Awaiting review', 'href' => route('admin.kyc'), 'link' => 'Review', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ];
    @endphp
    @foreach($overviewCards as $s)
    <div class="card p-5 flex items-start justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">{{ $s['label'] }}</p>
            <p class="mt-2 text-2xl font-bold text-neutral-900 dark:text-white">{{ $s['value'] }}</p>
            <p class="mt-1 text-xs text-neutral-500">{{ $s['meta'] }}</p>
            @if(!empty($s['href'] ?? null))
                <a href="{{ $s['href'] }}" class="mt-2 inline-block text-xs font-semibold text-neutral-900 dark:text-white underline underline-offset-2">{{ $s['link'] ?? 'Open' }} →</a>
            @endif
        </div>
        <div class="w-10 h-10 rounded bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-neutral-600 dark:text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $s['icon'] }}"/>
            </svg>
        </div>
    </div>
    @endforeach
</div>

{{-- Secondary quick links: fraud & merchants (compact) --}}
<div class="flex flex-wrap items-center gap-3 gap-y-2 mb-8 text-sm text-neutral-600 dark:text-neutral-400">
    <a href="{{ route('admin.fraud') }}" class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 dark:border-neutral-700 px-3 py-1.5 hover:border-neutral-900 dark:hover:border-neutral-500 transition">
        <span class="font-semibold text-neutral-900 dark:text-white">{{ number_format($stats['fraud_open']) }}</span>
        <span>open fraud flags</span>
    </a>
    <a href="{{ route('admin.merchants') }}" class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 dark:border-neutral-700 px-3 py-1.5 hover:border-neutral-900 dark:hover:border-neutral-500 transition">
        <span class="font-semibold text-neutral-900 dark:text-white">{{ number_format($stats['merchants_active']) }}</span>
        <span>active partner merchants</span>
    </a>
    <a href="{{ route('admin.withdrawals') }}" class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 dark:border-neutral-700 px-3 py-1.5 hover:border-neutral-900 dark:hover:border-neutral-500 transition">
        Withdrawals
    </a>
    <a href="{{ route('admin.cashbacks') }}" class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 dark:border-neutral-700 px-3 py-1.5 hover:border-neutral-900 dark:hover:border-neutral-500 transition">
        Cashback
    </a>
</div>

{{-- Marketplace: two summary cards only --}}
<div class="mb-8">
    <div class="mb-3">
        <h2 class="font-semibold text-sm text-neutral-900 dark:text-white">Marketplace</h2>
        <p class="text-xs text-neutral-500">All sellers — completed sales. Split: 2% buyer cashback, 1% admin fee, 97% to sellers.</p>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Gross sales volume</p>
            <p class="mt-2 text-2xl font-bold text-neutral-900 dark:text-white">ZMW {{ number_format($stats['marketplace_gross'], 2) }}</p>
            <ul class="mt-3 text-xs text-neutral-500 space-y-1">
                <li>Today: <span class="text-neutral-800 dark:text-neutral-200 font-medium">ZMW {{ number_format($stats['marketplace_gross_today'], 2) }}</span> · {{ number_format($stats['marketplace_sales_today_count']) }} sales</li>
                <li>{{ number_format($stats['marketplace_sales_count']) }} completed sales · avg order ZMW {{ number_format($stats['marketplace_average_order'], 2) }}</li>
                <li>{{ number_format($stats['items_sold_total']) }} units sold · {{ number_format($stats['products_total']) }} listings · {{ number_format($stats['stock_units_total']) }} stock (listed)</li>
            </ul>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Fees &amp; payouts (lifetime)</p>
            <dl class="mt-3 space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-neutral-500">Admin fee (1%)</dt>
                    <dd class="font-semibold text-neutral-900 dark:text-white tabular-nums">ZMW {{ number_format($stats['marketplace_admin_fee'], 2) }}</dd>
                </div>
                <p class="text-xs text-neutral-500">Today: ZMW {{ number_format($stats['marketplace_admin_fee_today'], 2) }}</p>
                <div class="flex justify-between gap-4">
                    <dt class="text-neutral-500">Marketplace cashback (2%)</dt>
                    <dd class="font-semibold text-neutral-900 dark:text-white tabular-nums">ZMW {{ number_format($stats['marketplace_cashback'], 2) }}</dd>
                </div>
                <p class="text-xs text-neutral-500">Today: ZMW {{ number_format($stats['marketplace_cashback_today'], 2) }}</p>
                <div class="flex justify-between gap-4 pt-1 border-t border-neutral-100 dark:border-neutral-800">
                    <dt class="text-neutral-500">To sellers (97%)</dt>
                    <dd class="font-semibold text-neutral-900 dark:text-white tabular-nums">ZMW {{ number_format($stats['marketplace_seller_net'], 2) }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-8">
    <div class="card overflow-hidden xl:col-span-2">
        <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800">
            <h2 class="font-semibold text-sm text-neutral-900 dark:text-white">Recent marketplace sales</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-100 dark:border-neutral-800">
                        <th class="px-5 py-3 text-left font-semibold">Product</th>
                        <th class="px-5 py-3 text-left font-semibold">Buyer</th>
                        <th class="px-5 py-3 text-left font-semibold">Seller</th>
                        <th class="px-5 py-3 text-right font-semibold">Gross</th>
                        <th class="px-5 py-3 text-right font-semibold">Admin fee</th>
                        <th class="px-5 py-3 text-right font-semibold">Cashback</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentProductSales as $sale)
                    <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                        <td class="px-5 py-3 text-neutral-800 dark:text-neutral-200">{{ $sale->product?->title ?? 'Product deleted' }}</td>
                        <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">{{ $sale->buyer?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">{{ $sale->seller?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-right font-medium text-neutral-900 dark:text-white">ZMW {{ number_format($sale->gross_amount, 2) }}</td>
                        <td class="px-5 py-3 text-right text-neutral-600">ZMW {{ number_format($sale->admin_fee, 2) }}</td>
                        <td class="px-5 py-3 text-right text-neutral-600">ZMW {{ number_format($sale->cashback_amount, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-neutral-400 text-sm">No marketplace sales yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800">
            <h2 class="font-semibold text-sm text-neutral-900 dark:text-white">Top sellers</h2>
        </div>
        <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
            @forelse($topSellers as $seller)
            <div class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-sm text-neutral-900 dark:text-white">{{ $seller->seller?->name ?? 'Seller deleted' }}</p>
                        <p class="mt-1 text-xs text-neutral-500">{{ number_format($seller->sales_count) }} sales</p>
                    </div>
                    <p class="text-sm font-bold text-neutral-900 dark:text-white">ZMW {{ number_format($seller->gross_total, 2) }}</p>
                </div>
                <p class="mt-2 text-xs text-neutral-500">Seller net: ZMW {{ number_format($seller->seller_net_total, 2) }}</p>
            </div>
            @empty
            <div class="p-8 text-center text-neutral-400 text-sm">No seller activity yet</div>
            @endforelse
        </div>
    </div>
</div>

{{-- Recent payments --}}
<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
        <h2 class="font-semibold text-sm text-neutral-900 dark:text-white">Recent payments</h2>
        <a href="{{ route('admin.payments') }}" class="text-xs text-neutral-500 hover:text-neutral-900 dark:hover:text-white">View all →</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                    <th class="px-5 py-3 text-left font-semibold">User</th>
                    <th class="px-5 py-3 text-left font-semibold">Merchant</th>
                    <th class="px-5 py-3 text-right font-semibold">Amount</th>
                    <th class="px-5 py-3 text-right font-semibold">Cashback</th>
                    <th class="px-5 py-3 text-center font-semibold">Status</th>
                    <th class="px-5 py-3 text-right font-semibold">Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentPayments as $p)
                <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                    <td class="px-5 py-3 text-neutral-800 dark:text-neutral-200">{{ $p->user?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">{{ $p->merchant?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-right font-medium text-neutral-900 dark:text-white">ZMW {{ number_format($p->amount, 2) }}</td>
                    <td class="px-5 py-3 text-right text-neutral-600">ZMW {{ number_format($p->cashback_amount ?? 0, 2) }}</td>
                    <td class="px-5 py-3 text-center">
                        <span class="badge badge-{{ strtolower($p->status?->value ?? 'pending') }}">
                            {{ $p->status?->value ?? '—' }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right text-neutral-500 text-xs">{{ $p->created_at->format('M d, H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-neutral-400 text-sm">No payments yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
