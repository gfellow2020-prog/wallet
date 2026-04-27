@extends('admin.layouts.app')
@section('title', 'Audit logs — ExtraCash Admin')
@section('page-title', 'Audit logs')
@section('breadcrumb', 'System audit trail (platform-wide)')

@section('content')
<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <form method="GET" class="flex gap-2 flex-1">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search action or type…"
                   class="flex-1 px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white max-w-md">
            <button type="submit" class="btn-secondary text-xs py-2">Search</button>
        </form>
        <div class="text-xs text-neutral-500">{{ $logs->total() }} log(s)</div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                    <th class="px-5 py-3 text-left">Time</th>
                    <th class="px-5 py-3 text-left">Action</th>
                    <th class="px-5 py-3 text-left">User</th>
                    <th class="px-5 py-3 text-left">Target</th>
                    <th class="px-5 py-3 text-left">IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                    <td class="px-5 py-3 text-xs text-neutral-500 whitespace-nowrap">{{ $log->created_at->format('M d, Y H:i:s') }}</td>
                    <td class="px-5 py-3 font-mono text-xs text-neutral-800 dark:text-neutral-200">{{ $log->action }}</td>
                    <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">
                        @if($log->user)
                            {{ $log->user->name }}<span class="block text-xs text-neutral-500">{{ $log->user->email }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-5 py-3 text-xs text-neutral-600 dark:text-neutral-400">
                        @if($log->auditable_type)
                            <span class="text-neutral-500">{{ class_basename($log->auditable_type) }}</span>
                            @if($log->auditable_id) #{{ $log->auditable_id }} @endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-5 py-3 text-xs text-neutral-500 font-mono">{{ $log->ip_address ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-5 py-8 text-center text-neutral-400 text-sm">No audit log entries yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
        <div class="px-5 py-4 border-t border-neutral-200 dark:border-neutral-800">{{ $logs->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
