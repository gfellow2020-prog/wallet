@extends('admin.layouts.app')
@section('title', 'Users — ExtraCash Admin')
@section('page-title', 'Users')
@section('breadcrumb', 'All registered accounts')

@section('content')

<div class="card overflow-hidden">
    {{-- Filters --}}
    <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <form method="GET" class="flex gap-2 flex-1">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name, email…"
                   class="flex-1 px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white max-w-xs">
            <button type="submit" class="btn-secondary text-xs py-2">Search</button>
        </form>
        <div class="text-xs text-neutral-500">{{ $users->total() }} users total</div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                    <th class="px-5 py-3 text-left">Name</th>
                    <th class="px-5 py-3 text-left">Email</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-center">KYC</th>
                    <th class="px-5 py-3 text-right">Wallet Balance</th>
                    <th class="px-5 py-3 text-center">Joined</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr
                    class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition cursor-pointer"
                    onclick="window.location='{{ route('admin.users.show', $user) }}'"
                >
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded bg-neutral-200 dark:bg-neutral-700 flex items-center justify-center text-xs font-bold text-neutral-700 dark:text-neutral-200">
                                {{ strtoupper(substr($user->name, 0, 2)) }}
                            </div>
                            <span class="font-medium text-neutral-900 dark:text-white">{{ $user->name }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">{{ $user->email }}</td>
                    <td class="px-5 py-3 text-center">
                        @if($user->suspended_at)
                            <span class="badge badge-rejected">Suspended</span>
                        @else
                            <span class="badge badge-approved">Active</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-center">
                        @php $kyc = $user->kycRecords?->last(); @endphp
                        <span class="badge badge-{{ strtolower($kyc?->status?->value ?? 'notsubmitted') }}">
                            {{ $kyc?->status?->value ?? 'Not Submitted' }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right font-medium text-neutral-900 dark:text-white">
                        ZMW {{ number_format($user->wallet?->available_balance ?? 0, 2) }}
                    </td>
                    <td class="px-5 py-3 text-center text-xs text-neutral-500">
                        {{ $user->created_at->format('M d, Y') }}
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.users.show', $user) }}"
                           class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 hover:text-neutral-900 dark:hover:text-white hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                           aria-label="View user details"
                           title="View user details"
                           onclick="event.stopPropagation();">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-5 py-8 text-center text-neutral-400">No users found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
    <div class="px-5 py-4 border-t border-neutral-100 dark:border-neutral-800">
        {{ $users->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection
