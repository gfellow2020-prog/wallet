@extends('admin.layouts.app')
@section('title', 'KYC Reviews — ExtraCash Admin')
@section('page-title', 'KYC Reviews')
@section('breadcrumb', 'Identity verification queue')

@section('content')

{{-- Stats bar --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    @foreach(['Pending'=>$counts['pending'],'Verified'=>$counts['verified'],'Rejected'=>$counts['rejected'],'Total'=>$counts['total']] as $label => $val)
    <div class="card p-4 text-center">
        <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ $label }}</p>
        <p class="mt-1 text-2xl font-bold text-neutral-900 dark:text-white">{{ number_format($val) }}</p>
    </div>
    @endforeach
</div>

<div class="card overflow-hidden">
    {{-- Filter tabs --}}
    <div class="px-5 pt-4 flex gap-1 border-b border-neutral-200 dark:border-neutral-800">
        @foreach(['all'=>'All','pending'=>'Pending','verified'=>'Verified','rejected'=>'Rejected'] as $val => $lbl)
        <a href="{{ route('admin.kyc', ['status' => $val]) }}"
           class="px-4 py-2 text-xs font-semibold rounded-t border-b-2 transition
                  {{ request('status', 'all') === $val
                     ? 'border-neutral-900 dark:border-white text-neutral-900 dark:text-white'
                     : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-300' }}">
            {{ $lbl }}
        </a>
        @endforeach
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                    <th class="px-5 py-3 text-left">User</th>
                    <th class="px-5 py-3 text-left">ID Type</th>
                    <th class="px-5 py-3 text-left">ID Number</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-center">Submitted</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $kyc)
                <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                    <td class="px-5 py-3">
                        <div>
                            <p class="font-medium text-neutral-900 dark:text-white">{{ $kyc->user?->name }}</p>
                            <p class="text-xs text-neutral-500">{{ $kyc->user?->email }}</p>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">{{ $kyc->id_type ?? '—' }}</td>
                    <td class="px-5 py-3 font-mono text-xs text-neutral-700 dark:text-neutral-300">{{ $kyc->id_number ?? '—' }}</td>
                    <td class="px-5 py-3 text-center">
                        <span class="badge badge-{{ strtolower($kyc->status?->value ?? 'pending') }}">
                            {{ $kyc->status?->value ?? '—' }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-center text-xs text-neutral-500">
                        {{ $kyc->created_at->format('M d, Y') }}
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            @if($kyc->status?->value === 'Pending')
                            <form method="POST" action="{{ route('admin.kyc.review', $kyc) }}" class="inline">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn-primary py-1.5 px-3 text-xs">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.kyc.review', $kyc) }}" class="inline">
                                @csrf
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn-secondary py-1.5 px-3 text-xs">Reject</button>
                            </form>
                            @else
                            <span class="text-xs text-neutral-400">Reviewed</span>
                            @endif
                            <a href="{{ route('admin.kyc.show', $kyc) }}" class="btn-secondary py-1.5 px-3 text-xs">View</a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-neutral-400">No KYC records found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($records->hasPages())
    <div class="px-5 py-4 border-t border-neutral-100 dark:border-neutral-800">
        {{ $records->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection
