<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PhoneVerificationController extends Controller
{
    public function __construct(
        protected OtpService $otp,
    ) {}

    public function show(Request $request)
    {
        $userId = $request->session()->get('phone_verify_user_id');
        $otpId = $request->session()->get('phone_verify_otp_id');

        if (! $userId || ! $otpId) {
            return redirect()->route('register.show');
        }

        $user = User::query()->find($userId);
        if (! $user) {
            return redirect()->route('register.show');
        }

        return view('auth.verify-phone', [
            'email' => $user->email,
            'phone_number' => $user->phone_number,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'otp_code' => ['required', 'string', 'max:12'],
        ]);

        $userId = $request->session()->get('phone_verify_user_id');
        $otpId = $request->session()->get('phone_verify_otp_id');

        if (! $userId || ! $otpId) {
            return redirect()->route('register.show');
        }

        /** @var User|null $user */
        $user = User::query()->find($userId);
        if (! $user) {
            return redirect()->route('register.show');
        }

        $ok = $this->otp->verify($user, (int) $otpId, 'phone_verify', (string) $request->input('otp_code'));
        if (! $ok) {
            return back()->withErrors(['otp_code' => 'Invalid or expired OTP.']);
        }

        $user->forceFill(['phone_verified_at' => now()])->save();

        $request->session()->forget(['phone_verify_user_id', 'phone_verify_otp_id']);

        Auth::login($user);

        return redirect()->route('wallet.home');
    }
}

