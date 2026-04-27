@extends('admin.layouts.app')
@section('title', 'KYC Detail — ExtraCash Admin')
@section('page-title', 'KYC Detail')
@section('breadcrumb', 'Review identity submission')

@section('content')

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 card p-6 space-y-5">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500 mb-1">User</p>
            <p class="font-semibold text-neutral-900 dark:text-white">{{ $kyc->user?->name }}</p>
            <p class="text-sm text-neutral-500">{{ $kyc->user?->email }}</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500 mb-1">ID Type</p>
                <p class="text-sm text-neutral-800 dark:text-neutral-200">{{ $kyc->id_type ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500 mb-1">ID Number</p>
                <p class="text-sm font-mono text-neutral-800 dark:text-neutral-200">{{ $kyc->id_number ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500 mb-1">Status</p>
                <span class="badge badge-{{ strtolower($kyc->status?->value ?? 'pending') }}">{{ $kyc->status?->value }}</span>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500 mb-1">Submitted</p>
                <p class="text-sm text-neutral-800 dark:text-neutral-200">{{ $kyc->created_at->format('M d, Y H:i') }}</p>
            </div>
        </div>
        @if($kyc->notes)
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500 mb-1">Notes</p>
            <p class="text-sm text-neutral-700 dark:text-neutral-300">{{ $kyc->notes }}</p>
        </div>
        @endif
    </div>

    <div class="space-y-4">
        @if($kyc->status?->value === 'Pending')
        <div class="card p-5 space-y-3">
            <h3 class="font-semibold text-sm text-neutral-900 dark:text-white">Actions</h3>
            <form method="POST" action="{{ route('admin.kyc.review', $kyc) }}">
                @csrf
                <textarea name="notes" placeholder="Optional notes…" rows="3"
                          class="w-full px-3 py-2 text-sm border rounded mb-3 bg-white dark:bg-neutral-900 dark:text-white"></textarea>
                <div class="flex gap-2">
                    <input type="hidden" name="action" id="kyc-action" value="approve">
                    <button type="submit" onclick="document.getElementById('kyc-action').value='approve'" class="btn-primary flex-1">Approve</button>
                    <button type="submit" onclick="document.getElementById('kyc-action').value='reject'" class="btn-secondary flex-1">Reject</button>
                </div>
            </form>
        </div>
        @endif

        <a href="{{ route('admin.kyc') }}" class="btn-secondary w-full justify-center">← Back to KYC List</a>
    </div>
</div>

@endsection
