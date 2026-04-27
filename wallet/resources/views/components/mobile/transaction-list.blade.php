@props(['transactions'])

<section>
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-sm font-bold tracking-wide text-gray-400">Recent Transactions</h2>
        <a href="{{ route('wallet.history') }}" class="text-xs font-semibold text-black hover:underline underline-offset-2">See all</a>
    </div>

    <div class="space-y-2.5">
        @forelse ($transactions as $transaction)
            @php
                $isCredit = $transaction->type === 'credit';
            @endphp

            <article class="flex items-center gap-3.5 rounded-lg bg-white p-3.5 ring-1 ring-gray-100 transition-all active:scale-[0.99]">
                {{-- Icon --}}
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-black">
                    @if ($isCredit)
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7-7-7 7"/></svg>
                    @else
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7 7 7-7"/></svg>
                    @endif
                </div>

                {{-- Details --}}
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-black">{{ $transaction->narration }}</p>
                    <div class="mt-0.5 flex items-center gap-1.5">
                        <p class="text-[11px] text-gray-400">{{ optional($transaction->transacted_at)->format('M d, Y · h:i A') }}</p>
                        @if (($transaction->gateway_status ?? 'local') !== 'local')
                            <span class="inline-flex items-center rounded-md border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-gray-500">
                                {{ $transaction->gateway_status }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Amount --}}
                <p class="shrink-0 text-sm font-bold text-black">
                    {{ $isCredit ? '+' : '-' }}K{{ number_format((float) $transaction->amount, 2) }}
                </p>
            </article>
        @empty
            <article class="flex flex-col items-center gap-3 rounded-lg bg-white p-8 text-center ring-1 ring-gray-100">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
                    <svg class="h-6 w-6 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/></svg>
                </div>
                <p class="text-sm font-medium text-black">No transactions yet</p>
                <p class="text-xs text-gray-400">Your activity will appear here</p>
            </article>
        @endforelse
    </div>
</section>
