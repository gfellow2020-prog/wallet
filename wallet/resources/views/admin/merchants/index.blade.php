@extends('admin.layouts.app')
@section('title', 'Partner merchants — ExtraCash Admin')
@section('page-title', 'Partner merchants')
@section('breadcrumb', 'Wallet pay-at-partner (not marketplace selling)')

@section('content')

<p class="text-sm text-neutral-600 dark:text-neutral-400 mb-4 max-w-3xl">
    <strong>Partner merchants</strong> are businesses users pay with their wallet (Pay flow, orders, payments). This is separate from the <strong>marketplace</strong>, where members buy and sell listings — use <a href="{{ route('admin.orders') }}" class="font-semibold text-neutral-900 dark:text-white underline underline-offset-2">marketplace orders</a> to review those sales.
</p>

<div class="flex justify-end mb-4">
    <button onclick="document.getElementById('add-merchant-modal').classList.remove('hidden')" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add partner merchant
    </button>
</div>

<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <form method="GET" class="flex gap-2 flex-1">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search partner merchants…"
                   class="flex-1 px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white max-w-xs">
            <button type="submit" class="btn-secondary text-xs py-2">Search</button>
        </form>
        <div class="text-xs text-neutral-500">{{ $merchants->total() }} partner merchant(s)</div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                    <th class="px-5 py-3 text-left">Name</th>
                    <th class="px-5 py-3 text-left">Category</th>
                    <th class="px-5 py-3 text-center">Cashback Rate</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-center">Orders</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($merchants as $merchant)
                <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            @if($merchant->logo_url)
                                <img src="{{ $merchant->logo_url }}" class="w-7 h-7 rounded object-cover" alt="">
                            @else
                                <div class="w-7 h-7 rounded bg-neutral-200 dark:bg-neutral-700 flex items-center justify-center text-xs font-bold">
                                    {{ strtoupper(substr($merchant->name, 0, 2)) }}
                                </div>
                            @endif
                            <span class="font-medium text-neutral-900 dark:text-white">{{ $merchant->name }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">{{ $merchant->category ?? '—' }}</td>
                    <td class="px-5 py-3 text-center font-medium text-neutral-900 dark:text-white">
                        {{ $merchant->cashback_eligible ? number_format(($merchant->cashback_rate ?? 0)*100, 1).'%' : '—' }}
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="badge {{ $merchant->is_active ? 'badge-verified' : 'badge-rejected' }}">
                            {{ $merchant->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-center text-neutral-600">{{ number_format($merchant->orders_count ?? 0) }}</td>
                    <td class="px-5 py-3 text-right">
                        <form method="POST" action="{{ route('admin.merchants.toggle', $merchant) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white underline underline-offset-2">
                                {{ $merchant->is_active ? 'Disable' : 'Enable' }}
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-neutral-400">No partner merchants found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($merchants->hasPages())
    <div class="px-5 py-4 border-t border-neutral-100 dark:border-neutral-800">
        {{ $merchants->withQueryString()->links() }}
    </div>
    @endif
</div>

{{-- Add Merchant Modal --}}
<div id="add-merchant-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
    <div class="card w-full max-w-lg">
        <div class="px-6 py-5 border-b border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
            <h3 class="font-bold text-neutral-900 dark:text-white text-sm">Add partner merchant</h3>
            <button onclick="document.getElementById('add-merchant-modal').classList.add('hidden')"
                    class="text-neutral-400 hover:text-neutral-900 dark:hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form method="POST" action="{{ route('admin.merchants.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-neutral-600 mb-1.5">Business name</label>
                <input type="text" name="name" required class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-neutral-600 mb-1.5">Code <span class="font-normal text-neutral-400">(optional, A–Z / 0–9)</span></label>
                <input type="text" name="code" maxlength="20" class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white" placeholder="Auto-generated if empty">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-neutral-600 mb-1.5">Category</label>
                    <input type="text" name="category" class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white" placeholder="e.g. Supermarket">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-neutral-600 mb-1.5">Cashback Rate</label>
                    <input type="number" name="cashback_rate" step="0.001" min="0" max="1" value="0.02"
                           class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
                </div>
            </div>
            <div class="flex items-center gap-3">
                <input type="checkbox" name="cashback_eligible" value="1" id="cb_eligible" checked class="w-4 h-4 rounded">
                <label for="cb_eligible" class="text-sm text-neutral-700 dark:text-neutral-300">Cashback eligible</label>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('add-merchant-modal').classList.add('hidden')" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Add partner merchant</button>
            </div>
        </form>
    </div>
</div>

@endsection
