@extends('admin.layouts.app')
@section('title', 'Adjustments — ExtraCash Admin')
@section('page-title', 'Adjustments')
@section('breadcrumb', 'Admin balance adjustments (credit / debit)')

@section('content')
<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800">
        <p class="text-xs text-neutral-500">{{ $adjustments->total() }} record(s) · platform-wide</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                    <th class="px-5 py-3 text-left">Date</th>
                    <th class="px-5 py-3 text-left">User</th>
                    <th class="px-5 py-3 text-center">Direction</th>
                    <th class="px-5 py-3 text-right">Amount</th>
                    <th class="px-5 py-3 text-left">Reason</th>
                    <th class="px-5 py-3 text-left">Admin</th>
                </tr>
            </thead>
            <tbody>
                @forelse($adjustments as $adj)
                <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                    <td class="px-5 py-3 text-xs text-neutral-500 whitespace-nowrap">{{ $adj->created_at->format('M d, Y H:i') }}</td>
                    <td class="px-5 py-3">
                        <span class="text-neutral-900 dark:text-white">{{ $adj->user?->name ?? '—' }}</span>
                        <span class="block text-xs text-neutral-500">{{ $adj->user?->email ?? '' }}</span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="badge badge-{{ $adj->direction === 'credit' ? 'approved' : 'rejected' }}">{{ ucfirst($adj->direction) }}</span>
                    </td>
                    <td class="px-5 py-3 text-right font-medium">ZMW {{ number_format($adj->amount, 2) }}</td>
                    <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400 max-w-xs truncate" title="{{ $adj->reason }}">{{ $adj->reason }}</td>
                    <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">{{ $adj->admin?->name ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-neutral-400 text-sm">No adjustments recorded yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($adjustments->hasPages())
        <div class="px-5 py-4 border-t border-neutral-200 dark:border-neutral-800">{{ $adjustments->links() }}</div>
    @endif
</div>
@endsection
