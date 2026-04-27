@extends('admin.layouts.app')
@section('title', 'Cashback — ExtraCash Admin')
@section('page-title', 'Cashback')
@section('breadcrumb', 'All cashback transactions')

@section('content')

<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex gap-3 items-center justify-between flex-wrap">
        <form method="GET" class="flex gap-2 flex-wrap">
            <select name="status" class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
                <option value="">All Statuses</option>
                @foreach(['Pending','Locked','Available','Reversed','Expired'] as $s)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-secondary text-xs py-2">Filter</button>
        </form>
        <div class="text-xs text-neutral-500">{{ $cashbacks->total() }} records</div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                    <th class="px-5 py-3 text-left">User</th>
                    <th class="px-5 py-3 text-left">Merchant</th>
                    <th class="px-5 py-3 text-right">Cashback</th>
                    <th class="px-5 py-3 text-right">Order Amount</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-center">Releases At</th>
                    <th class="px-5 py-3 text-right">Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cashbacks as $cb)
                <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                    <td class="px-5 py-3 text-neutral-800 dark:text-neutral-200">{{ $cb->user?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">{{ $cb->merchant?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-right font-medium text-neutral-900 dark:text-white">ZMW {{ number_format($cb->cashback_amount, 2) }}</td>
                    <td class="px-5 py-3 text-right text-neutral-600">ZMW {{ number_format($cb->order?->total_amount ?? 0, 2) }}</td>
                    <td class="px-5 py-3 text-center">
                        <span class="badge badge-{{ strtolower($cb->status?->value ?? 'pending') }}">
                            {{ $cb->status?->value ?? '—' }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-center text-xs text-neutral-500">
                        {{ $cb->release_at ? \Carbon\Carbon::parse($cb->release_at)->format('M d, Y') : '—' }}
                    </td>
                    <td class="px-5 py-3 text-right text-xs text-neutral-500">{{ $cb->created_at->format('M d, H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-5 py-8 text-center text-neutral-400">No cashback records found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($cashbacks->hasPages())
    <div class="px-5 py-4 border-t border-neutral-100 dark:border-neutral-800">
        {{ $cashbacks->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection
