<x-layouts.mobile title="Messages">

    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-lg font-extrabold tracking-tight text-black">Messages</h2>
    </div>

    <div class="space-y-2">
        @forelse($conversations as $c)
            @php
                $other = $c->participants->firstWhere('user_id', '!=', $me->id)?->user;
            @endphp

            <a href="{{ route('messages.show', $c) }}"
               class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 bg-white px-4 py-3 transition active:scale-[0.99]">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-black text-white text-sm font-bold">
                        {{ strtoupper(substr($other?->name ?? 'U', 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-black">{{ $other?->name ?? 'User' }}</p>
                        <p class="truncate text-xs text-gray-400">{{ $c->last_message_at?->diffForHumans() ?? '—' }}</p>
                    </div>
                </div>
                <div class="text-gray-300">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
            </a>
        @empty
            <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                No conversations yet.
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $conversations->links() }}
    </div>

    <div class="h-4"></div>

</x-layouts.mobile>

