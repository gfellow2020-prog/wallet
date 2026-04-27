<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'expo_token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'in:ios,android'],
            'device_id' => ['nullable', 'string', 'max:128'],
        ]);

        $token = trim($data['expo_token']);
        if (! $this->looksLikeExpoToken($token)) {
            return response()->json(['message' => 'Invalid Expo push token.'], 422);
        }

        $record = UserPushToken::query()->updateOrCreate(
            ['expo_token' => $token],
            [
                'user_id' => $user->id,
                'platform' => $data['platform'] ?? null,
                'device_id' => $data['device_id'] ?? null,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ]
        );

        return response()->json([
            'message' => 'Push token registered.',
            'token' => [
                'id' => $record->id,
                'platform' => $record->platform,
                'device_id' => $record->device_id,
                'last_seen_at' => $record->last_seen_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = UserPushToken::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $token) {
            return response()->json(['message' => 'Token not found.'], 404);
        }

        $token->update(['revoked_at' => now()]);

        return response()->json(['message' => 'Push token revoked.']);
    }

    private function looksLikeExpoToken(string $token): bool
    {
        // Supports classic: ExponentPushToken[xxxx] and new style: ExpoPushToken[xxxx]
        return preg_match('/^(ExponentPushToken|ExpoPushToken)\\[[^\\]]+\\]$/', $token) === 1;
    }
}

