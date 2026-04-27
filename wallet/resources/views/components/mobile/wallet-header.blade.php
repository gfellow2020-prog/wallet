@props([
    'userName' => 'User',
    'currency' => 'ZMW',
    'currencySymbol' => 'K',
    'balance' => 0,
    'cardNumber' => '**** **** **** 0000',
    'expiry' => '12/30',
])

<header class="mb-8">
    {{-- Top bar: logo left, bell + avatar right --}}
    <div class="mb-5 flex items-center justify-between">
        {{-- Text logo --}}
        <div class="flex items-center gap-2">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-black text-sm font-black text-white select-none">G</span>
            <span class="text-lg font-extrabold tracking-tight text-black">ExtraCash</span>
        </div>

        {{-- Bell + Avatar --}}
        <div class="flex items-center gap-2.5">
            {{-- Bell button --}}
            <button type="button" class="relative flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-black transition active:scale-95 active:bg-gray-200">
                <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </button>

            {{-- Avatar --}}
            <a href="{{ route('profile') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-black text-sm font-bold text-white transition active:scale-95 active:opacity-80">
                {{ strtoupper(substr($userName, 0, 1)) }}
            </a>
        </div>
    </div>

    {{-- ATM Card --}}
    <div class="relative overflow-hidden rounded-lg bg-black px-5 py-4 text-white shadow-lg">

        {{-- Top row: brand + chip --}}
        <div class="mb-4 flex items-start justify-between">
            <div>
                <p class="text-sm font-bold tracking-wide">ExtraCash</p>
                <p class="mt-0.5 text-[10px] font-medium uppercase tracking-[0.25em] text-white/40">Premium Card</p>
            </div>

            {{-- Chip --}}
            <div class="flex h-8 w-11 items-center justify-center rounded-md border border-white/20 bg-white/10">
                <div class="h-4 w-6 rounded-sm border border-white/20 bg-white/10"></div>
            </div>
        </div>

        {{-- Card number --}}
        <p class="mb-4 font-mono text-[15px] tracking-[0.22em] text-white/80">{{ $cardNumber }}</p>

        {{-- Balance + Expiry --}}
        <div class="flex items-end justify-between">
            <div>
                <p class="text-[9px] font-semibold uppercase tracking-[0.3em] text-white/40">Available Balance</p>
                <p class="mt-1 text-[28px] font-extrabold leading-none tracking-tight">
                    {{ $currencySymbol }}{{ number_format((float) $balance, 2) }}
                </p>
                <p class="mt-1 text-[11px] font-medium text-white/40">{{ $currency }} · Zambian Kwacha</p>
            </div>
            <div class="text-right">
                <p class="text-[9px] font-semibold uppercase tracking-[0.3em] text-white/40">Expires</p>
                <p class="mt-0.5 font-mono text-sm tracking-wider text-white/70">{{ $expiry }}</p>
            </div>
        </div>

        {{-- Card holder --}}
        <div class="mt-3 flex items-center justify-between border-t border-white/10 pt-3">
            <p class="text-[10px] font-semibold uppercase tracking-[0.28em] text-white/40">{{ strtoupper($userName) }}</p>
            {{-- Two overlapping circles (monochrome card brand mark) --}}
            <div class="flex -space-x-2">
                <div class="h-5 w-5 rounded-full bg-white/30 ring-1 ring-white/10"></div>
                <div class="h-5 w-5 rounded-full bg-white/60 ring-1 ring-white/10"></div>
            </div>
        </div>
    </div>
</header>
