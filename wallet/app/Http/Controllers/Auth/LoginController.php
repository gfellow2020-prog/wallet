<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            /** @var User|null $user */
            $user = $request->user();
            if ($user && $user->suspended_at !== null) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->withErrors(['email' => 'Your account has been suspended. Please contact support.'])
                    ->onlyInput('email');
            }
            if ($user && $user->phone_verified_at === null) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // Send user to OTP verify page; OTP is issued during registration.
                return redirect()
                    ->route('phone.verify.show')
                    ->withErrors(['email' => 'Please verify your mobile number before logging in.']);
            }

            $request->session()->regenerate();

            $isRoleAdmin = $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole([
                'super_admin',
                'ops',
                'risk',
                'compliance',
                'finance',
                'support',
                'merchant_admin',
            ]);
            $isAllowlistAdmin = $user && method_exists($user, 'isAdmin') && $user->isAdmin();

            if ($isRoleAdmin || $isAllowlistAdmin) {
                return redirect()->intended(route('admin.dashboard'));
            }

            return redirect()->intended(route('wallet.index'));
        }

        return back()->withErrors(['email' => 'The provided credentials do not match our records.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('wallet.home');
    }
}
