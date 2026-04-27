<x-layouts.mobile title="Profile">

    {{-- Page header --}}
    <div class="mb-6 flex items-center gap-3">
        <h2 class="text-lg font-extrabold tracking-tight text-black">Profile</h2>
    </div>

    {{-- Avatar + name card (clickable) --}}
    <a href="{{ route('profile') }}" class="mb-4 flex items-center gap-4 rounded-lg bg-black p-5 text-white transition active:scale-[0.99]">
        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-white/15 text-xl font-extrabold text-white">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </div>
        <div class="min-w-0">
            <p class="truncate text-base font-bold">{{ $user->name }}</p>
            <p class="truncate text-[13px] text-white/50">{{ $user->email }}</p>
        </div>
    </a>

    {{-- Wallet info --}}
    <div class="mb-4 rounded-lg bg-gray-50 ring-1 ring-gray-100">
        <div class="flex items-center justify-between px-5 py-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">Wallet Balance</p>
                <p class="mt-0.5 text-2xl font-extrabold text-black">
                    K{{ number_format((float) ($wallet->balance ?? 0), 2) }}
                </p>
            </div>
            <div class="text-right">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">Currency</p>
                <p class="mt-0.5 text-sm font-bold text-black">{{ $wallet->currency ?? 'ZMW' }}</p>
            </div>
        </div>
    </div>

    {{-- Account details --}}
    <div class="mb-4 divide-y divide-gray-100 rounded-lg bg-gray-50 ring-1 ring-gray-100">
        <div class="flex items-center justify-between px-5 py-3.5">
            <span class="text-sm text-gray-400">Full Name</span>
            <span class="text-sm font-semibold text-black">{{ $user->name }}</span>
        </div>
        <div class="flex items-center justify-between px-5 py-3.5">
            <span class="text-sm text-gray-400">Email</span>
            <span class="max-w-[55%] truncate text-sm font-semibold text-black">{{ $user->email }}</span>
        </div>
        <div class="flex items-center justify-between px-5 py-3.5">
            <span class="text-sm text-gray-400">Member since</span>
            <span class="text-sm font-semibold text-black">{{ $user->created_at->format('M Y') }}</span>
        </div>
    </div>

    {{-- Logout --}}
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit"
            class="w-full rounded-lg border border-gray-200 bg-gray-50 py-3.5 text-sm font-semibold text-black transition active:bg-gray-100">
            Sign out
        </button>
    </form>

</x-layouts.mobile>
