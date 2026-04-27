@extends('admin.layouts.app')
@section('title', 'Marketplace finance — ExtraCash Admin')
@section('page-title', 'Marketplace finance')
@section('breadcrumb', 'Marketplace revenue splits and payouts')

@section('content')

<div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-4">
    <div class="card p-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Gross</p>
        <p class="mt-2 text-2xl font-bold text-neutral-900 dark:text-white">ZMW {{ number_format($totals['gross'] ?? 0, 2) }}</p>
        <p class="mt-1 text-xs text-neutral-500">{{ number_format($totals['count'] ?? 0) }} sale(s)</p>
    </div>
    <div class="card p-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Admin fee (1%)</p>
        <p class="mt-2 text-2xl font-bold text-neutral-900 dark:text-white">ZMW {{ number_format($totals['admin_fee'] ?? 0, 2) }}</p>
    </div>
    <div class="card p-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Cashback (2%)</p>
        <p class="mt-2 text-2xl font-bold text-neutral-900 dark:text-white">ZMW {{ number_format($totals['cashback'] ?? 0, 2) }}</p>
    </div>
    <div class="card p-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Seller net (97%)</p>
        <p class="mt-2 text-2xl font-bold text-neutral-900 dark:text-white">ZMW {{ number_format($totals['seller_net'] ?? 0, 2) }}</p>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex flex-col lg:flex-row gap-3 items-start lg:items-center justify-between">
        <form method="GET" class="flex gap-2 flex-wrap items-center">
            <input type="date" name="date_from" value="{{ request('date_from', $range['from']->toDateString()) }}"
                   class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
            <input type="date" name="date_to" value="{{ request('date_to', $range['to']->toDateString()) }}"
                   class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Reference, product, buyer, seller…"
                   class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white w-56">
            <select name="status" class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
                <option value="">All Statuses</option>
                @foreach(($statuses ?? []) as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-secondary text-xs py-2">Filter</button>
            <a href="{{ route('admin.finance.marketplace.export', request()->query()) }}"
               class="btn-secondary text-xs py-2">Export CSV</a>
        </form>
        <div class="text-xs text-neutral-500">{{ $sales->total() }} records</div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                    <th class="px-5 py-3 text-left">Reference</th>
                    <th class="px-5 py-3 text-left">Product</th>
                    <th class="px-5 py-3 text-left">Buyer</th>
                    <th class="px-5 py-3 text-left">Seller</th>
                    <th class="px-5 py-3 text-right">Gross</th>
                    <th class="px-5 py-3 text-right">Admin fee</th>
                    <th class="px-5 py-3 text-right">Cashback</th>
                    <th class="px-5 py-3 text-right">Seller net</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-right">Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sales as $s)
                <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                    <td class="px-5 py-3 font-mono text-xs text-neutral-600 dark:text-neutral-400">
                        {{ $s->reference ?? '—' }}
                    </td>
                    <td class="px-5 py-3 text-neutral-800 dark:text-neutral-200">
                        {{ $s->product?->title ?? '—' }}
                    </td>
                    <td class="px-5 py-3">
                        <div>
                            <p class="font-medium text-neutral-900 dark:text-white">{{ $s->buyer?->name ?? '—' }}</p>
                            <p class="text-xs text-neutral-500">{{ $s->buyer?->email ?? '' }}</p>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        <div>
                            <p class="font-medium text-neutral-900 dark:text-white">{{ $s->seller?->name ?? '—' }}</p>
                            <p class="text-xs text-neutral-500">{{ $s->seller?->email ?? '' }}</p>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-right font-medium text-neutral-900 dark:text-white">ZMW {{ number_format($s->gross_amount ?? 0, 2) }}</td>
                    <td class="px-5 py-3 text-right text-neutral-600">ZMW {{ number_format($s->admin_fee ?? 0, 2) }}</td>
                    <td class="px-5 py-3 text-right text-neutral-600">ZMW {{ number_format($s->cashback_amount ?? 0, 2) }}</td>
                    <td class="px-5 py-3 text-right text-neutral-600">ZMW {{ number_format($s->seller_net ?? 0, 2) }}</td>
                    <td class="px-5 py-3 text-center">
                        <span class="badge badge-{{ strtolower($s->status ?? 'pending') }}">
                            {{ $s->status ?? '—' }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right text-xs text-neutral-500">{{ $s->created_at?->format('M d, H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="10" class="px-5 py-8 text-center text-neutral-400">No sales found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($sales->hasPages())
    <div class="px-5 py-4 border-t border-neutral-100 dark:border-neutral-800">
        {{ $sales->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection

