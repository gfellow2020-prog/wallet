@extends('admin.layouts.app')
@section('title', 'Payments — ExtraCash Admin')
@section('page-title', 'Payments')
@section('breadcrumb', 'All payment transactions')

@section('content')

<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-neutral-200 dark:border-neutral-800 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <form method="GET" class="flex gap-2 flex-wrap">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Reference, user, partner merchant…"
                   class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white w-52">
            <select name="status" class="px-3 py-2 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
                <option value="">All Statuses</option>
                @foreach(\App\Enums\PaymentStatus::cases() as $c)
                <option value="{{ $c->value }}" {{ request('status') === $c->value ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-secondary text-xs py-2">Filter</button>
        </form>
        <div class="text-xs text-neutral-500">{{ $payments->total() }} records</div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-100 dark:border-neutral-800">
                    <th class="px-5 py-3 text-left">Reference</th>
                    <th class="px-5 py-3 text-left">User</th>
                    <th class="px-5 py-3 text-left">Partner merchant</th>
                    <th class="px-5 py-3 text-right">Amount</th>
                    <th class="px-5 py-3 text-right">Cashback</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-right">Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $p)
                <tr class="border-b border-neutral-50 dark:border-neutral-800/50 hover:bg-neutral-50 dark:hover:bg-neutral-900/40 transition">
                    <td class="px-5 py-3 font-mono text-xs text-neutral-600 dark:text-neutral-400">{{ $p->payment_reference ?? '—' }}</td>
                    <td class="px-5 py-3 text-neutral-800 dark:text-neutral-200">{{ $p->user?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-neutral-600 dark:text-neutral-400">{{ $p->merchant?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-right font-medium text-neutral-900 dark:text-white">ZMW {{ number_format($p->amount, 2) }}</td>
                    <td class="px-5 py-3 text-right text-neutral-600">ZMW {{ number_format($p->cashback?->cashback_amount ?? 0, 2) }}</td>
                    <td class="px-5 py-3 text-center">
                        <span class="badge badge-{{ strtolower($p->status?->value ?? 'pending') }}">
                            {{ $p->status?->value ?? '—' }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right text-xs text-neutral-500">{{ $p->created_at->format('M d, H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-5 py-8 text-center text-neutral-400">No payments found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($payments->hasPages())
    <div class="px-5 py-4 border-t border-neutral-100 dark:border-neutral-800">
        {{ $payments->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection
