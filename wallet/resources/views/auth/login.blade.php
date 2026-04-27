<x-layouts.auth title="Sign in — ExtraCash Wallet">

    <h1 class="mb-1 text-2xl font-extrabold tracking-tight text-black">Welcome back</h1>
    <p class="mb-8 text-[13px] text-gray-400">Sign in to access your wallet</p>

    <form method="POST" action="{{ route('login.perform') }}" class="space-y-4">
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

        <div class="flex flex-col gap-1.5">
            <label for="password" class="text-[13px] font-semibold text-gray-700 uppercase tracking-widest">Password</label>
            <input
                id="password" name="password" type="password"
                required autocomplete="current-password"
                placeholder="••••••••"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3.5 text-sm text-black placeholder-gray-300 outline-none transition focus:border-black focus:bg-white focus:ring-0"
            />
        </div>

        <div class="flex items-center justify-between pt-1">
            <label class="flex cursor-pointer items-center gap-2 text-[13px] text-gray-500">
                <input type="checkbox" name="remember"
                    class="h-4 w-4 rounded border-gray-300 accent-black" />
                Remember me
            </label>
            <a href="{{ route('password.request') }}"
               class="text-[13px] font-semibold text-black underline-offset-2 hover:underline">
                Forgot password?
            </a>
        </div>

        <button type="submit"
            class="mt-2 w-full rounded-lg bg-black py-4 text-sm font-bold text-white tracking-wide transition active:scale-[0.98]">
            Sign in
        </button>
    </form>

    <p class="mt-8 text-center text-[13px] text-gray-400">
        No account?
        <a href="{{ route('register.show') }}" class="font-semibold text-black underline-offset-2 hover:underline">Create one</a>
    </p>

</x-layouts.auth>
