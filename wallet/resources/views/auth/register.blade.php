<x-layouts.auth title="Create account — ExtraCash Wallet">

    <h1 class="mb-1 text-2xl font-extrabold tracking-tight text-black">Create account</h1>
    <p class="mb-8 text-[13px] text-gray-400">Join ExtraCash and start transacting instantly</p>

    <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
        @csrf

        <div class="flex flex-col gap-1.5">
            <label for="name" class="text-[13px] font-semibold text-gray-700 uppercase tracking-widest">Full name</label>
            <input
                id="name" name="name" type="text"
                value="{{ old('name') }}"
                required autocomplete="name"
                placeholder="John Banda"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3.5 text-sm text-black placeholder-gray-300 outline-none transition focus:border-black focus:bg-white focus:ring-0"
            />
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="email" class="text-[13px] font-semibold text-gray-700 uppercase tracking-widest">Email</label>
            <input
                id="email" name="email" type="email"
                value="{{ old('email') }}"
                required autocomplete="email"
                placeholder="you@example.com"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3.5 text-sm text-black placeholder-gray-300 outline-none transition focus:border-black focus:bg-white focus:ring-0"
            />
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="phone_number" class="text-[13px] font-semibold text-gray-700 uppercase tracking-widest">Mobile number</label>
            <input
                id="phone_number" name="phone_number" type="text"
                value="{{ old('phone_number') }}"
                required autocomplete="tel"
                placeholder="0977 000 000"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3.5 text-sm text-black placeholder-gray-300 outline-none transition focus:border-black focus:bg-white focus:ring-0"
            />
            @error('phone_number')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="password" class="text-[13px] font-semibold text-gray-700 uppercase tracking-widest">Password</label>
            <input
                id="password" name="password" type="password"
                required autocomplete="new-password"
                placeholder="min. 6 characters"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3.5 text-sm text-black placeholder-gray-300 outline-none transition focus:border-black focus:bg-white focus:ring-0"
            />
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="password_confirmation" class="text-[13px] font-semibold text-gray-700 uppercase tracking-widest">Confirm password</label>
            <input
                id="password_confirmation" name="password_confirmation" type="password"
                required autocomplete="new-password"
                placeholder="repeat password"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3.5 text-sm text-black placeholder-gray-300 outline-none transition focus:border-black focus:bg-white focus:ring-0"
            />
        </div>

        <button type="submit"
            class="mt-2 w-full rounded-lg bg-black py-4 text-sm font-bold text-white tracking-wide transition active:scale-[0.98]">
            Create account
        </button>
    </form>

    <p class="mt-8 text-center text-[13px] text-gray-400">
        Already have an account?
        <a href="{{ route('login') }}" class="font-semibold text-black underline-offset-2 hover:underline">Sign in</a>
    </p>

</x-layouts.auth>
