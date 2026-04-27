<section class="mb-8">
    <h2 class="mb-4 text-sm font-bold tracking-wide text-gray-400">Quick Actions</h2>
    <div class="grid grid-cols-4 gap-2">
        {{-- Fund --}}
        <a href="{{ route('wallet.fund') }}" class="group flex flex-col items-center gap-2 rounded-lg bg-white p-3 ring-1 ring-gray-100 transition-all active:scale-95">
            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-black text-white transition group-hover:bg-gray-800">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            </div>
            <span class="text-[11px] font-semibold text-black">Fund</span>
        </a>

        {{-- Send --}}
        <a href="{{ route('wallet.send') }}" class="group flex flex-col items-center gap-2 rounded-lg bg-white p-3 ring-1 ring-gray-100 transition-all active:scale-95">
            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-black text-white transition group-hover:bg-gray-800">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.27 3.13a.3.3 0 01.41-.36l17.05 8.5a.3.3 0 010 .54l-17.05 8.5a.3.3 0 01-.41-.36L6 12zm0 0h8"/></svg>
            </div>
            <span class="text-[11px] font-semibold text-black">Send</span>
        </a>

        {{-- Pay --}}
        <a href="{{ route('wallet.pay') }}" class="group flex flex-col items-center gap-2 rounded-lg bg-white p-3 ring-1 ring-gray-100 transition-all active:scale-95">
            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-black text-white transition group-hover:bg-gray-800">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <span class="text-[11px] font-semibold text-black">Pay</span>
        </a>

        {{-- History --}}
        <a href="{{ route('wallet.history') }}" class="group flex flex-col items-center gap-2 rounded-lg bg-white p-3 ring-1 ring-gray-100 transition-all active:scale-95">
            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-black text-white transition group-hover:bg-gray-800">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <span class="text-[11px] font-semibold text-black">History</span>
        </a>
    </div>
</section>
