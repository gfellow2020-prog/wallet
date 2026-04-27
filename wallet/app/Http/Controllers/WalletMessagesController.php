<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WalletMessagesController extends Controller
{
    private function me(): ?User
    {
        /** @var User|null $u */
        $u = Auth::user();

        return $u;
    }

    private function ensureParticipant(int $userId, int $conversationId): bool
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
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

    public function index(Request $request): View
    {
        $me = $this->me();
        abort_if(! $me, 403);

        $conversations = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $me->id)->whereNull('archived_at'))
            ->with(['participants.user:id,name,email'])
            ->orderByDesc('last_message_at')
            ->paginate(25);

        return view('wallet.messages.index', compact('conversations', 'me'));
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $me = $this->me();
        abort_if(! $me, 403);

        abort_unless($this->ensureParticipant($me->id, $conversation->id), 403);

        $conversation->load(['participants.user:id,name,email']);
        $other = $conversation->participants->firstWhere('user_id', '!=', $me->id)?->user;

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->with(['attachments', 'sender:id,name'])
            ->orderBy('id')
            ->limit(200)
            ->get();

        return view('wallet.messages.show', compact('conversation', 'messages', 'me', 'other'));
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse
    {
        $me = $this->me();
        abort_if(! $me, 403);

        abort_unless($this->ensureParticipant($me->id, $conversation->id), 403);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $body = isset($data['body']) ? trim((string) $data['body']) : '';
        $hasImage = $request->hasFile('image');
        if ($body === '' && ! $hasImage) {
            return redirect()
                ->route('messages.show', $conversation)
                ->withErrors(['body' => 'Message body or image is required.']);
        }

        $otherId = (int) ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $me->id)
            ->value('user_id');

        if ($otherId > 0 && $this->usersBlockedEitherWay($me->id, $otherId)) {
            return redirect()
                ->route('messages.show', $conversation)
                ->withErrors(['body' => 'You cannot message this user.']);
        }

        DB::transaction(function () use ($request, $conversation, $me, $body) {
            $m = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $me->id,
                'body' => $body !== '' ? $body : null,
            ]);

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
                }

                MessageAttachment::query()->create([
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
        });

        return redirect()->route('messages.show', $conversation)->with('success', 'Message sent.');
    }

    public function downloadAttachment(Request $request, MessageAttachment $attachment)
    {
        $me = $this->me();
        abort_if(! $me, 403);

        $message = Message::query()->findOrFail($attachment->message_id);
        abort_unless($this->ensureParticipant($me->id, $message->conversation_id), 403);

        $disk = $attachment->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($attachment->path), 404);

        $stream = Storage::disk($disk)->readStream($attachment->path);
        abort_unless(is_resource($stream), 404);

        $filename = basename($attachment->path);
        $mime = $attachment->mime ?: 'application/octet-stream';

        return response()->streamDownload(function () use ($stream) {
            fpassthru($stream);
        }, $filename, ['Content-Type' => $mime]);
    }
}

