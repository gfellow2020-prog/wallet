<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageRead;
use App\Models\MessageReport;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MessagingController extends Controller
{
    public function __construct(
        protected NotificationService $notifications,
    ) {}

    private function ensureParticipant(User $user, Conversation $conversation): bool
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function usersBlockedEitherWay(int $a, int $b): bool
    {
        return UserBlock::query()
            ->where(function ($q) use ($a, $b) {
                $q->where('blocker_id', $a)->where('blocked_id', $b);
            })
            ->orWhere(function ($q) use ($a, $b) {
                $q->where('blocker_id', $b)->where('blocked_id', $a);
            })
            ->exists();
    }

    public function conversations(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rows = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id)->whereNull('archived_at'))
            ->with([
                'participants.user:id,name,email',
            ])
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();

        $items = $rows->map(function (Conversation $c) use ($user) {
            $other = $c->participants->firstWhere('user_id', '!=', $user->id)?->user;

            return [
                'id' => $c->id,
                'type' => $c->type,
                'last_message_at' => $c->last_message_at,
                'other_user' => $other ? [
                    'id' => $other->id,
                    'name' => $other->name,
                    'email' => $other->email,
                ] : null,
            ];
        });

        return response()->json(['conversations' => $items]);
    }

    public function directConversation(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'recipient_user_id' => ['required', 'integer', 'exists:users,id', 'different:'.$user->id],
        ]);

        $recipientId = (int) $data['recipient_user_id'];

        $existing = Conversation::query()
            ->where('type', 'direct')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $recipientId))
            ->first();

        if ($existing) {
            return response()->json(['conversation_id' => $existing->id]);
        }

        $conversation = DB::transaction(function () use ($user, $recipientId) {
            $c = Conversation::query()->create(['type' => 'direct']);

            ConversationParticipant::query()->create([
                'conversation_id' => $c->id,
                'user_id' => $user->id,
                'joined_at' => now(),
            ]);

            ConversationParticipant::query()->create([
                'conversation_id' => $c->id,
                'user_id' => $recipientId,
                'joined_at' => now(),
            ]);

            return $c;
        });

        return response()->json(['conversation_id' => $conversation->id], 201);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->ensureParticipant($user, $conversation)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $afterId = max(0, (int) $request->integer('after_id', 0));
        $limit = max(1, min((int) $request->integer('limit', 50), 100));

        $msgs = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('id', '>', $afterId)
            ->with(['attachments', 'sender:id,name'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $items = $msgs->map(function (Message $m) {
            return [
                'id' => $m->id,
                'sender' => [
                    'id' => $m->sender_id,
                    'name' => $m->sender?->name,
                ],
                'body' => $m->body,
                'created_at' => $m->created_at,
                'attachments' => $m->attachments->map(fn (MessageAttachment $a) => [
                    'id' => $a->id,
                    'mime' => $a->mime,
                    'width' => $a->width,
                    'height' => $a->height,
                    'size_bytes' => $a->size_bytes,
                    'url' => url("/api/message-attachments/{$a->id}"),
                ])->values(),
            ];
        });

        return response()->json(['messages' => $items]);
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->ensureParticipant($user, $conversation)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $body = isset($data['body']) ? trim((string) $data['body']) : '';
        $hasImage = $request->hasFile('image');
        if ($body === '' && ! $hasImage) {
            return response()->json(['message' => 'Message body or image is required.'], 422);
        }

        $other = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $user->id)
            ->first();

        $otherUserId = (int) ($other?->user_id ?? 0);
        if ($otherUserId > 0 && $this->usersBlockedEitherWay($user->id, $otherUserId)) {
            return response()->json(['message' => 'You cannot message this user.'], 403);
        }

        $message = DB::transaction(function () use ($request, $conversation, $user, $body) {
            $m = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => $body !== '' ? $body : null,
            ]);

            $attachment = null;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $path = $file->store("messages/{$conversation->id}", ['disk' => 'local']);

                $w = null;
                $h = null;
                $size = null;
                $mime = null;
                try {
                    $size = $file->getSize();
                    $mime = $file->getMimeType();
                    $info = @getimagesize($file->getRealPath());
                    if (is_array($info)) {
                        $w = (int) ($info[0] ?? 0) ?: null;
                        $h = (int) ($info[1] ?? 0) ?: null;
                    }
                } catch (\Throwable) {
                    // best-effort metadata only
                }

                $attachment = MessageAttachment::query()->create([
                    'message_id' => $m->id,
                    'disk' => 'local',
                    'path' => $path,
                    'mime' => $mime,
                    'size_bytes' => $size !== false ? $size : null,
                    'width' => $w,
                    'height' => $h,
                ]);
            }

            $conversation->update([
                'last_message_id' => $m->id,
                'last_message_at' => now(),
            ]);

            return [$m, $attachment];
        });

        /** @var Message $m */
        $m = $message[0];
        /** @var MessageAttachment|null $attachment */
        $attachment = $message[1];

        if ($otherUserId > 0) {
            $recipient = User::query()->find($otherUserId);
            if ($recipient) {
                $title = 'New message';
                $bodyText = $m->body ?: 'Sent you an image.';
                $this->notifications->notifyUser(
                    $recipient,
                    'message_new',
                    $title,
                    $bodyText,
                    data: [
                        'conversation_id' => $conversation->id,
                        'message_id' => $m->id,
                        'sender_id' => $user->id,
                        'sender_name' => $user->name,
                    ],
                    sendEmail: false,
                    sendPush: true,
                    dedupeKey: null,
                    isSensitive: false,
                );
            }
        }

        return response()->json([
            'message' => [
                'id' => $m->id,
                'body' => $m->body,
                'created_at' => $m->created_at,
                'attachments' => $attachment ? [[
                    'id' => $attachment->id,
                    'url' => url("/api/message-attachments/{$attachment->id}"),
                ]] : [],
            ],
        ], 201);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->ensureParticipant($user, $conversation)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'last_read_message_id' => ['required', 'integer', 'min:1'],
        ]);

        $lastId = (int) $data['last_read_message_id'];

        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update([
                'last_read_message_id' => $lastId,
                'last_read_at' => now(),
            ]);

        return response()->json(['message' => 'OK']);
    }

    public function blockUser(Request $request, User $user): JsonResponse
    {
        /** @var User $me */
        $me = $request->user();

        if ($me->id === $user->id) {
            return response()->json(['message' => 'Invalid'], 422);
        }

        UserBlock::query()->firstOrCreate([
            'blocker_id' => $me->id,
            'blocked_id' => $user->id,
        ]);

        return response()->json(['message' => 'OK']);
    }

    public function unblockUser(Request $request, User $user): JsonResponse
    {
        /** @var User $me */
        $me = $request->user();

        UserBlock::query()
            ->where('blocker_id', $me->id)
            ->where('blocked_id', $user->id)
            ->delete();

        return response()->json(['message' => 'OK']);
    }

    public function reportMessage(Request $request, Message $message): JsonResponse
    {
        /** @var User $me */
        $me = $request->user();

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:140'],
        ]);

        $conversation = Conversation::query()->find($message->conversation_id);
        if (! $conversation || ! $this->ensureParticipant($me, $conversation)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        MessageReport::query()->firstOrCreate([
            'reporter_id' => $me->id,
            'message_id' => $message->id,
        ], [
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json(['message' => 'OK']);
    }

    public function downloadAttachment(Request $request, MessageAttachment $attachment)
    {
        /** @var User $me */
        $me = $request->user();

        $message = Message::query()->find($attachment->message_id);
        if (! $message) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $conversation = Conversation::query()->find($message->conversation_id);
        if (! $conversation || ! $this->ensureParticipant($me, $conversation)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $disk = $attachment->disk ?: 'local';
        if (! Storage::disk($disk)->exists($attachment->path)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $stream = Storage::disk($disk)->readStream($attachment->path);
        if (! is_resource($stream)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $filename = basename($attachment->path);
        $mime = $attachment->mime ?: 'application/octet-stream';

        return response()->streamDownload(function () use ($stream) {
            fpassthru($stream);
        }, $filename, [
            'Content-Type' => $mime,
        ]);
    }
}

