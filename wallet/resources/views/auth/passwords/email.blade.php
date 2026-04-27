<x-layouts.auth title="Forgot Password — ExtraCash Wallet">

    <h1 class="mb-1 text-2xl font-extrabold tracking-tight text-black">Forgot password?</h1>
    <p class="mb-8 text-[13px] text-gray-400">Enter your email and we'll send you a reset link</p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

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

        <button type="submit"
            class="mt-2 w-full rounded-lg bg-black py-4 text-sm font-bold text-white tracking-wide transition active:scale-[0.98]">
            Send reset link
        </button>
    </form>

    <p class="mt-8 text-center text-[13px] text-gray-400">
        <a href="{{ route('login') }}" class="font-semibold text-black underline-offset-2 hover:underline">← Back to sign in</a>
    </p>

</x-layouts.auth>
