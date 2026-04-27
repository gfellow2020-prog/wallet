<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight user directory — only exposes the fields needed for UI:
 * id, name, extracash_number, profile photo. Never email / phone / NRC.
 *
 * Rate-limited via `auth:sanctum` — callers must already be logged in to
 * enumerate handles. Uses a single not-found message for invalid length and
 * missing users to reduce user enumeration; outcomes are logged server-side.
 */
class UserLookupController extends Controller
{
    public function byExtracashNumber(Request $request): JsonResponse
    {
        $data = $request->validate([
            'extracash_number' => 'required|string|max:20',
        ]);

        $normalized = User::normalizeExtracashNumber($data['extracash_number']);
        if (strlen($normalized) < 6) {
            Log::info('users.lookup', [
                'outcome' => 'invalid_format',
            ]);

            return response()->json(['message' => UserDirectory::EXTRA_CASH_LOOKUP_NOT_FOUND], 404);
        }

        $user = User::where('extracash_number', $normalized)->first();
        if (! $user) {
            Log::info('users.lookup', [
                'outcome' => 'not_found',
            ]);

            return response()->json(['message' => UserDirectory::EXTRA_CASH_LOOKUP_NOT_FOUND], 404);
        }

        if ($user->id === $request->user()->id) {
            Log::info('users.lookup', [
                'outcome' => 'self',
                'target_user_id' => $user->id,
            ]);

            return response()->json([
                'message' => UserDirectory::EXTRA_CASH_LOOKUP_SELF,
            ], 422);
        }

        Log::info('users.lookup', [
            'outcome' => 'found',
            'target_user_id' => $user->id,
        ]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'extracash_number' => $user->extracash_number,
            'profile_photo_url' => $user->profile_photo_path
                ? url('/storage/'.ltrim($user->profile_photo_path, '/'))
                : null,
        ]);
    }
}
