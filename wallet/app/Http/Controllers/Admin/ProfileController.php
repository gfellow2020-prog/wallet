<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user, 403);

        $user->loadMissing(['roles', 'permissions']);

        return view('admin.profile.show', [
            'user' => $user,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $user->update([
            'name' => $data['name'],
        ]);

        return redirect()
            ->route('admin.profile.show')
            ->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user, 403);

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], (string) $user->password)) {
            return redirect()
                ->route('admin.profile.show')
                ->with('error', 'Current password is incorrect.');
        }

        if (Hash::check((string) $data['password'], (string) $user->password)) {
            return redirect()
                ->route('admin.profile.show')
                ->with('error', 'Choose a new password you haven’t used before.');
        }

        $user->update([
            'password' => $data['password'],
        ]);

        return redirect()
            ->route('admin.profile.show')
            ->with('success', 'Password updated.');
    }
}

