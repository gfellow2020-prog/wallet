<x-layouts.auth title="Verify phone — ExtraCash Wallet">

    <h1 class="mb-1 text-2xl font-extrabold tracking-tight text-black">Verify your mobile number</h1>
    <p class="mb-6 text-[13px] text-gray-400">
        We sent an OTP to <span class="font-semibold text-black">{{ $phone_number }}</span>.
        Enter it below to activate your account.
    </p>

    <form method="POST" action="{{ route('phone.verify.perform') }}" class="space-y-4">
        @csrf

        <div class="flex flex-col gap-1.5">
            <label for="otp_code" class="text-[13px] font-semibold text-gray-700 uppercase tracking-widest">OTP code</label>
            <input
                id="otp_code" name="otp_code" type="text"
                value="{{ old('otp_code') }}"
                required autocomplete="one-time-code"
                placeholder="123456"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3.5 text-sm text-black placeholder-gray-300 outline-none transition focus:border-black focus:bg-white focus:ring-0"
            />
            @error('otp_code')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
            class="mt-2 w-full rounded-lg bg-black py-4 text-sm font-bold text-white tracking-wide transition active:scale-[0.98]">
            Verify & Continue
        </button>
    </form>

    <p class="mt-8 text-center text-[13px] text-gray-400">
        Signed up with the wrong number?
        <a href="{{ route('register.show') }}" class="font-semibold text-black underline-offset-2 hover:underline">Start over</a>
    </p>

</x-layouts.auth>

