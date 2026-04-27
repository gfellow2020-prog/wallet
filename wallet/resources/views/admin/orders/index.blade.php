@extends('admin.layouts.app')
@section('title', 'Marketplace orders — ExtraCash Admin')
@section('page-title', 'Orders')
@section('breadcrumb', 'Product sales across all buyers & sellers')

@section('content')
<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex flex-col gap-4">
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
            <form method="GET" class="flex flex-wrap gap-2 items-center">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Product title or reference…"
                       class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white min-w-[200px]">
                <select name="status" class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
                    <option value="">All statuses</option>
                    <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                    <option value="refunded" @selected(request('status') === 'refunded')>Refunded</option>
                </select>
                <button type="submit" class="btn-secondary text-xs py-2">Filter</button>
            </form>
            <div class="text-xs text-neutral-500">{{ $orders->total() }} order(s)</div>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                    <th class="px-5 py-3 text-left">Reference</th>
                    <th class="px-5 py-3 text-left">Product</th>
                    <th class="px-5 py-3 text-center">Qty</th>
                    <th class="px-5 py-3 text-left">Buyer</th>
                    <th class="px-5 py-3 text-left">Seller</th>
                    <th class="px-5 py-3 text-right">Gross</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-right">Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                    <td class="px-5 py-3 font-mono text-xs text-neutral-600 dark:text-neutral-400">{{ $order->reference }}</td>
                    <td class="px-5 py-3 text-neutral-900 dark:text-white">{{ $order->product?->title ?? '—' }}</td>
                    <td class="px-5 py-3 text-center">{{ $order->quantity ?? 1 }}</td>
                    <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">{{ $order->buyer?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">{{ $order->seller?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-right font-medium">ZMW {{ number_format($order->gross_amount, 2) }}</td>
                    <td class="px-5 py-3 text-center">
                        <span class="badge badge-{{ $order->status === 'completed' ? 'verified' : 'pending' }}">{{ $order->status }}</span>
                    </td>
                    <td class="px-5 py-3 text-right text-xs text-neutral-500">{{ $order->created_at->format('M d, Y H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-5 py-8 text-center text-neutral-400 text-sm">No marketplace orders yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
        <div class="px-5 py-4 border-t border-neutral-200 dark:border-neutral-800">{{ $orders->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
