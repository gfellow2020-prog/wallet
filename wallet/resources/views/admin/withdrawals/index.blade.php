@extends('admin.layouts.app')
@section('title', 'Withdrawals — ExtraCash Admin')
@section('page-title', 'Withdrawals')
@section('breadcrumb', 'Manage withdrawal requests')

@section('content')

<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex gap-3 flex-wrap items-center justify-between">
        <form method="GET" class="flex gap-2 flex-wrap">
            <select name="status" class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
                <option value="">All Statuses</option>
                @foreach(['Requested','UnderReview','Approved','Processing','Paid','Rejected','Failed','Reversed'] as $s)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-secondary text-xs py-2">Filter</button>
        </form>
        <div class="text-xs text-neutral-500">{{ $withdrawals->total() }} requests</div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                    <th class="px-5 py-3 text-left">User</th>
                    <th class="px-5 py-3 text-right">Amount</th>
                    <th class="px-5 py-3 text-left">Method</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-center">Requested</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($withdrawals as $w)
                <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                    <td class="px-5 py-3">
                        <div>
                            <p class="font-medium text-neutral-900 dark:text-white">{{ $w->user?->name ?? '—' }}</p>
                            <p class="text-xs text-neutral-500">{{ $w->user?->email ?? '' }}</p>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-right font-medium text-neutral-900 dark:text-white">ZMW {{ number_format($w->amount, 2) }}</td>
                    <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">{{ $w->method ?? 'Mobile Money' }}</td>
                    <td class="px-5 py-3 text-center">
                        <span class="badge badge-{{ strtolower($w->status?->value ?? 'requested') }}">
                            {{ $w->status?->value ?? '—' }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-center text-xs text-neutral-500">{{ $w->created_at->format('M d, Y') }}</td>
                    <td class="px-5 py-3 text-right">
                        @if(in_array($w->status?->value, ['Requested','UnderReview']))
                        <div class="flex items-center justify-end gap-2">
                            <form method="POST" action="{{ route('admin.withdrawals.action', $w) }}" class="inline">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn-primary py-1.5 px-3 text-xs">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.withdrawals.action', $w) }}" class="inline">
                                @csrf
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn-secondary py-1.5 px-3 text-xs">Reject</button>
                            </form>
                        </div>
                        @else
                        <span class="text-xs text-neutral-400">{{ $w->status?->value }}</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-neutral-400">No withdrawals found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($withdrawals->hasPages())
    <div class="px-5 py-4 border-t border-neutral-100 dark:border-neutral-800">
        {{ $withdrawals->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection
