<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetLinkMail;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    public function __construct(
        protected NotificationService $notifications,
    ) {}

    public function show()
    {
        return view('auth.passwords.email');
    }

    public function sendResetLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return back()->with('status', 'If your email exists in our system, we have emailed your password reset link.');
        }

        // create or update token
        $token = Str::random(64);

        DB::table('password_resets')->updateOrInsert(
            ['email' => $data['email']],
            ['token' => $token, 'created_at' => now()]
        );

        // send simple email with reset link
        $url = url('/password/reset/'.$token.'?email='.urlencode($data['email']));

        Mail::to($data['email'])->queue(new PasswordResetLinkMail($url));

        $this->notifications->notifyUser(
            $user,
            'security_password_reset_requested',
            'Password reset requested',
            'A password reset was requested for your account. If this wasn’t you, you can ignore the email and consider changing your password.',
            [],
            sendEmail: false,
            sendPush: true,
        );

        return back()->with('status', 'If your email exists in our system, we have emailed your password reset link.');
    }
}
