<x-layouts.mobile title="Fund Wallet — ExtraCash">
    {{-- Page header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('wallet.index') }}" class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-500 transition active:scale-95 hover:bg-gray-200">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-lg font-extrabold tracking-tight text-gray-900">Fund Wallet</h1>
    </div>

    {{-- Info banner --}}
    <div class="mb-6 flex items-start gap-2.5 rounded-lg bg-gray-50 p-3.5 ring-1 ring-gray-200">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-black" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
        <p class="text-xs font-medium leading-relaxed text-black">A mobile money prompt will be sent to your phone. Approve it to fund your wallet.</p>
    </div>

    <form method="POST" action="{{ route('wallet.fund.store') }}" class="space-y-5">
        @csrf

        {{-- Phone Number --}}
        <div>
            <label for="phone_number" class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">Mobile Money Number</label>
            <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-gray-300">🇿🇲</span>
                <input
                    type="tel"
                    id="phone_number"
                    name="phone_number"
                    value="{{ old('phone_number', '260') }}"
                    placeholder="260971234567"
                    required
                    maxlength="12"
                    pattern="260[0-9]{9}"
                    class="w-full rounded-lg border-0 bg-white py-3.5 pl-12 pr-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-200 placeholder:text-gray-300 focus:ring-2 focus:ring-black"
                />
            </div>
            @error('phone_number') <p class="mt-1.5 text-xs font-medium text-black">{{ $message }}</p> @enderror
        </div>

        {{-- Amount input --}}
        <div>
            <label for="amount" class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">Amount</label>
            <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-xl font-bold text-gray-300">K</span>
                <input
                    type="number"
                    id="amount"
                    name="amount"
                    min="1"
                    step="0.01"
                    value="{{ old('amount') }}"
                    placeholder="0.00"
                    required
                    class="w-full rounded-lg border-0 bg-white py-4 pl-10 pr-4 text-2xl font-extrabold text-gray-900 shadow-sm ring-1 ring-gray-200 placeholder:text-gray-200 focus:ring-2 focus:ring-black"
                />
            </div>
            @error('amount') <p class="mt-1.5 text-xs font-medium text-black">{{ $message }}</p> @enderror
        </div>

        {{-- Quick amount chips --}}
        <div class="flex flex-wrap gap-2">
            @foreach ([100, 500, 1000, 5000] as $chip)
                <button type="button" onclick="document.getElementById('amount').value='{{ $chip }}'" class="rounded-md bg-gray-100 px-4 py-2 text-xs font-bold text-black transition hover:bg-gray-200 active:scale-95">
                    K{{ number_format($chip) }}
                </button>
            @endforeach
        </div>

        {{-- Narration --}}
        <div>
            <label for="narration" class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">Description <span class="normal-case font-normal">(optional)</span></label>
            <input
                type="text"
                id="narration"
                name="narration"
                value="{{ old('narration') }}"
                placeholder="e.g. Salary top-up"
                maxlength="120"
                class="w-full rounded-lg border-0 bg-white px-4 py-3.5 text-sm font-medium text-gray-900 shadow-sm ring-1 ring-gray-200 placeholder:text-gray-300 focus:ring-2 focus:ring-black"
            />
        </div>

        {{-- Submit --}}
        <button type="submit" class="w-full rounded-lg bg-black py-4 text-sm font-bold text-white transition active:scale-[0.98]">
            <span class="flex items-center justify-center gap-2">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
                Fund via Mobile Money
            </span>
        </button>
    </form>
</x-layouts.mobile>
