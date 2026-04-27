@extends('admin.layouts.app')
@section('title', 'Wallets — ExtraCash Admin')
@section('page-title', 'Wallets')
@section('breadcrumb', 'All user wallets (platform-wide)')

@section('content')
<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <form method="GET" class="flex gap-2 flex-1">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by user name or email…"
                   class="flex-1 px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white max-w-md">
            <button type="submit" class="btn-secondary text-xs py-2">Search</button>
        </form>
        <div class="text-xs text-neutral-500">{{ $wallets->total() }} wallet(s)</div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                    <th class="px-5 py-3 text-left">User</th>
                    <th class="px-5 py-3 text-left">Email</th>
                    <th class="px-5 py-3 text-right">Available</th>
                    <th class="px-5 py-3 text-right">Pending</th>
                    <th class="px-5 py-3 text-center">Currency</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($wallets as $wallet)
                <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                    <td class="px-5 py-3 font-medium text-neutral-900 dark:text-white">{{ $wallet->user?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">{{ $wallet->user?->email ?? '—' }}</td>
                    <td class="px-5 py-3 text-right">ZMW {{ number_format($wallet->available_balance, 2) }}</td>
                    <td class="px-5 py-3 text-right">ZMW {{ number_format($wallet->pending_balance, 2) }}</td>
                    <td class="px-5 py-3 text-center text-neutral-500">{{ $wallet->currency ?? 'ZMW' }}</td>
                    <td class="px-5 py-3 text-right">
                        @if($wallet->user)
                            <a href="{{ route('admin.users.show', $wallet->user) }}" class="text-xs font-semibold text-neutral-900 dark:text-white underline underline-offset-2">View user →</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-neutral-400 text-sm">No wallets found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($wallets->hasPages())
        <div class="px-5 py-4 border-t border-neutral-200 dark:border-neutral-800">{{ $wallets->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
