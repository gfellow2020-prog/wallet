@extends('admin.layouts.app')
@section('title', 'Fraud Flags — ExtraCash Admin')
@section('page-title', 'Fraud Flags')
@section('breadcrumb', 'Risk management — account risk queue')

@section('content')

@php
    $status = $status ?? 'open';
    $q = $q ?? null;
@endphp

<div class="px-0 mb-4 flex flex-col sm:flex-row gap-3 sm:items-end sm:justify-between">
    <form method="GET" class="flex flex-wrap items-end gap-2">
        <div>
            <label class="block text-xs font-semibold text-neutral-500 mb-1">Status</label>
            <select name="status" onchange="this.form.submit()" class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
                <option value="open" @selected($status==='open')>Open</option>
                <option value="resolved" @selected($status==='resolved')>Resolved</option>
                <option value="all" @selected($status==='all')>All</option>
            </select>
        </div>
        <div class="flex-1 min-w-[200px] max-w-sm">
            <label class="block text-xs font-semibold text-neutral-500 mb-1">Search user</label>
            <div class="flex gap-2">
                <input type="search" name="q" value="{{ $q }}" placeholder="Name or email…"
                       class="flex-1 px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
                <button type="submit" class="btn-secondary text-xs py-2">Search</button>
            </div>
        </div>
    </form>
    <p class="text-xs text-neutral-500">{{ $flags->total() }} flag(s)</p>
    </div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                    <th class="px-5 py-3 text-left">User</th>
                    <th class="px-5 py-3 text-left">Flag type</th>
                    <th class="px-5 py-3 text-left">Notes</th>
                    <th class="px-5 py-3 text-center">DB status</th>
                    <th class="px-5 py-3 text-center">Flagged at</th>
                    <th class="px-5 py-3 text-center">Resolved at</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($flags as $flag)
                <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                    <td class="px-5 py-3">
                        <div>
                            @if($flag->user)
                            <a href="{{ route('admin.users.show', $flag->user) }}" class="font-medium text-neutral-900 dark:text-white hover:underline">
                                {{ $flag->user->name }}
                            </a>
                            @else
                            <p class="font-medium text-neutral-500">—</p>
                            @endif
                            <p class="text-xs text-neutral-500">{{ $flag->user?->email ?? '' }}</p>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-neutral-800 dark:text-neutral-200">
                        <span class="font-mono text-xs">{{ $flag->flag_type }}</span>
                    </td>
                    <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400 max-w-sm">
                        <span class="line-clamp-2" title="{{ $flag->notes }}">{{ $flag->notes ?? '—' }}</span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="text-xs text-neutral-600 dark:text-neutral-300">{{ $flag->status }}</span>
                    </td>
                    <td class="px-5 py-3 text-center text-xs text-neutral-500 whitespace-nowrap">{{ $flag->created_at->format('M d, Y H:i') }}</td>
                    <td class="px-5 py-3 text-center text-xs text-neutral-500 whitespace-nowrap">
                        {{ $flag->resolved_at?->format('M d, Y H:i') ?? '—' }}
                    </td>
                    <td class="px-5 py-3 text-right">
                        @if(!$flag->resolved_at)
                        <form method="POST" action="{{ route('admin.fraud.resolve', $flag) }}" class="inline">
                            @csrf
                            <button type="submit" class="btn-secondary py-1.5 px-3 text-xs">Mark resolved</button>
                        </form>
                        @else
                        <span class="text-xs text-neutral-400">
                            @if($flag->reviewer)
                            by {{ $flag->reviewer->name }}
                            @else
                            Resolved
                            @endif
                        </span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-5 py-8 text-center text-neutral-400">No fraud flags found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($flags->hasPages())
    <div class="px-5 py-4 border-t border-neutral-100 dark:border-neutral-800">
        {{ $flags->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection
