<x-layouts.mobile title="Chat">

    <div class="mb-5 flex items-center justify-between gap-3">
        <a href="{{ route('messages.index') }}" class="text-sm font-semibold text-black">← Back</a>
        <div class="flex items-center gap-2 min-w-0">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-black text-white text-xs font-bold">
                {{ strtoupper(substr($other?->name ?? 'U', 0, 1)) }}
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-bold text-black">{{ $other?->name ?? 'User' }}</p>
                <p class="truncate text-[11px] text-gray-400">{{ $other?->email ?? '' }}</p>
            </div>
        </div>
        <div></div>
    </div>

    <div class="space-y-3">
        @forelse($messages as $m)
            @php $mine = $m->sender_id === $me->id; @endphp
            <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[85%] rounded-2xl px-4 py-3 {{ $mine ? 'bg-black text-white' : 'bg-gray-100 text-black' }}">
                    @if($m->body)
                        <p class="text-sm whitespace-pre-wrap break-words">{{ $m->body }}</p>
                    @endif

                    @if($m->attachments->count())
                        <div class="mt-2 space-y-2">
                            @foreach($m->attachments as $a)
                                <a href="{{ route('messages.attachments.download', $a) }}" class="block">
                                    <img src="{{ route('messages.attachments.download', $a) }}"
                                         alt="attachment"
                                         class="w-full rounded-lg border border-white/10 {{ $mine ? '' : 'border-gray-200' }}">
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <p class="mt-2 text-[10px] {{ $mine ? 'text-white/50' : 'text-gray-400' }}">
                        {{ $m->created_at?->format('M d, H:i') }}
                    </p>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                No messages yet.
            </div>
        @endforelse
    </div>

    <div class="mt-6 rounded-lg border border-gray-100 bg-white p-4">
        <form method="POST" action="{{ route('messages.send', $conversation) }}" enctype="multipart/form-data" class="space-y-3">
            @csrf

            <div>
                <textarea name="body" rows="3" placeholder="Type a message…"
                          class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-black focus:border-black focus:ring-0">{{ old('body') }}</textarea>
                @error('body')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center justify-between gap-3">
                <input type="file" name="image" accept="image/*" class="text-xs text-gray-500">
                @error('image')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-black py-3 text-sm font-semibold text-white transition active:scale-[0.99]">
                Send
            </button>
        </form>
    </div>

    <div class="h-10"></div>

</x-layouts.mobile>

