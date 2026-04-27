<x-layouts.mobile title="Pay Bills — ExtraCash">
    {{-- Page header --}}
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('wallet.index') }}" class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-500 transition active:scale-95 hover:bg-gray-200">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-lg font-extrabold tracking-tight text-gray-900">Pay Bills</h1>
    </div>

    {{-- Balance pill --}}
    <div class="mb-6 inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-gray-50 px-4 py-2 text-xs font-bold text-black">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/></svg>
        Available: K{{ number_format((float) $balance, 2) }}
    </div>

    <form method="POST" action="{{ route('wallet.pay.store') }}" class="space-y-5">
        @csrf

        {{-- Biller selection --}}
        <div>
            <label for="biller" class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">Biller / Service</label>
            <div class="relative">
                <select id="biller" name="biller" required class="w-full appearance-none rounded-lg border-0 bg-white px-4 py-3.5 pr-10 text-sm font-medium text-gray-900 shadow-sm ring-1 ring-gray-200 focus:ring-2 focus:ring-black">
                    <option value="" disabled selected>Choose a biller</option>
                    <option value="ZESCO Electricity" {{ old('biller') === 'ZESCO Electricity' ? 'selected' : '' }}>⚡ ZESCO Electricity</option>
                    <option value="Airtel Airtime" {{ old('biller') === 'Airtel Airtime' ? 'selected' : '' }}>📱 Airtel Airtime</option>
                    <option value="MTN Airtime" {{ old('biller') === 'MTN Airtime' ? 'selected' : '' }}>📱 MTN Airtime</option>
                    <option value="DSTV Subscription" {{ old('biller') === 'DSTV Subscription' ? 'selected' : '' }}>📺 DSTV Subscription</option>
                    <option value="Zamtel Data" {{ old('biller') === 'Zamtel Data' ? 'selected' : '' }}>🌐 Zamtel Data</option>
                    <option value="Water Utilities" {{ old('biller') === 'Water Utilities' ? 'selected' : '' }}>💧 Water Utilities</option>
                </select>
                <svg class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </div>
            @error('biller') <p class="mt-1.5 text-xs font-medium text-black">{{ $message }}</p> @enderror
        </div>

        {{-- Amount --}}
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
                    max="{{ $balance }}"
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
            @foreach ([50, 100, 250, 500] as $chip)
                <button type="button" onclick="document.getElementById('amount').value='{{ $chip }}'" class="rounded-md bg-gray-100 px-4 py-2 text-xs font-bold text-black transition hover:bg-gray-200 active:scale-95">
                    K{{ number_format($chip) }}
                </button>
            @endforeach
        </div>

        {{-- Submit --}}
        <button type="submit" class="w-full rounded-lg bg-black py-4 text-sm font-bold text-white transition active:scale-[0.98]">
            Pay Now
        </button>
    </form>
</x-layouts.mobile>
