<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
    public function show(Request $request, $token)
    {
        $email = $request->query('email');

        return view('auth.passwords.reset', ['token' => $token, 'email' => $email]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:6'],
        ]);

        $record = DB::table('password_resets')
            ->where('email', $data['email'])
            ->where('token', $data['token'])
            ->first();

        if (! $record) {
            return back()->withErrors(['email' => 'Invalid token or email.']);
        }

        if ($record->created_at && Carbon::parse($record->created_at)->lt(now()->subHour())) {
            return back()->withErrors(['email' => 'Token has expired.']);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return back()->withErrors(['email' => 'No user with that email.']);
        }

        if (Hash::check((string) $data['password'], (string) $user->password)) {
            return back()->withErrors(['password' => 'Choose a new password you haven’t used before.']);
        }

        $user->password = $data['password'];
        $user->save();

        DB::table('password_resets')->where('email', $data['email'])->delete();

        Auth::login($user);

        return redirect()->route('wallet.home');
    }
}
