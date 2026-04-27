<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    public function __construct(
        protected OtpService $otp,
    ) {}

    public function show()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'phone_number' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $phone = preg_replace('/\D+/', '', (string) $data['phone_number']) ?? '';
        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            $phone = '260'.substr($phone, 1);
        } elseif (strlen($phone) === 9) {
            $phone = '260'.$phone;
        }
        if (! (str_starts_with($phone, '260') && strlen($phone) === 12)) {
            return back()->withErrors(['phone_number' => 'Enter a valid Zambian mobile number.'])->withInput();
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $phone,
            'phone_verified_at' => null,
            'password' => $data['password'],
        ]);

        Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'currency' => 'ZMW']
        );

        $challenge = $this->otp->createAndSend($user, 'phone_verify', [
            'phone_number' => $phone,
        ]);

        $request->session()->put('phone_verify_user_id', $user->id);
        $request->session()->put('phone_verify_otp_id', $challenge['otp']->id);

        return redirect()->route('phone.verify.show');
    }
}
