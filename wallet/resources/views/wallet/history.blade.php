<x-layouts.mobile title="Transaction History — ExtraCash">
    {{-- Page header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('wallet.index') }}" class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-500 transition active:scale-95 hover:bg-gray-200">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div class="flex-1">
            <h1 class="text-lg font-extrabold tracking-tight text-gray-900">Transaction History</h1>
            <p class="text-xs font-medium text-gray-400">{{ $transactions->count() }} transactions</p>
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="mb-6 grid grid-cols-2 gap-3">
        @php
            $totalIn  = $transactions->where('type', 'credit')->sum('amount');
            $totalOut = $transactions->where('type', 'debit')->sum('amount');
        @endphp
        <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-100">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total In</p>
            <p class="mt-1 text-lg font-extrabold text-black">K{{ number_format((float) $totalIn, 2) }}</p>
        </div>
        <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-100">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total Out</p>
            <p class="mt-1 text-lg font-extrabold text-black">K{{ number_format((float) $totalOut, 2) }}</p>
        </div>
    </div>

    <x-mobile.transaction-list :transactions="$transactions" />
</x-layouts.mobile>
