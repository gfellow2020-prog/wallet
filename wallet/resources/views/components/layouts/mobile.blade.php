<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <title>{{ $title ?? 'ExtraCash Wallet' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-white text-black antialiased">

    <div class="relative mx-auto flex min-h-screen w-full max-w-md flex-col bg-white shadow-sm shadow-gray-100">

        {{-- Toast: Success --}}
        @if (session('success'))
            <div class="mx-4 mt-3 flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-black text-white">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
                <p class="text-sm font-medium text-black">{{ session('success') }}</p>
            </div>
        @endif

        {{-- Toast: Errors --}}
        @if ($errors->any())
            <div class="mx-4 mt-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <ul class="space-y-1 text-sm text-black">
                    @foreach ($errors->all() as $error)
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-black" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-.75-5a.75.75 0 001.5 0V9a.75.75 0 00-1.5 0v4zm.75-6.5a.75.75 0 100 1.5.75.75 0 000-1.5z" clip-rule="evenodd"/></svg>
                            {{ $error }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <main class="flex-1 px-5 pb-8 pt-5">
            {{ $slot }}
        </main>

        {{-- Bottom Nav --}}
        <nav class="sticky bottom-0 z-40 border-t border-gray-100 bg-white px-4 pb-[env(safe-area-inset-bottom)]">
            <div class="flex items-center justify-around py-2">

                {{-- Home --}}
                @php $homeActive = request()->routeIs('wallet.home','wallet.index'); @endphp
                <a href="{{ route('wallet.index') }}"
                   class="flex flex-col items-center gap-1 transition active:scale-95">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl transition
                        {{ $homeActive ? 'bg-black text-white' : 'text-gray-400 hover:bg-gray-100' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="{{ $homeActive ? '2.2' : '1.8' }}" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7m-9-5v12a1 1 0 001 1h3m4 0a1 1 0 001-1v-4a1 1 0 00-1-1h-4a1 1 0 00-1 1v4"/>
                        </svg>
                    </span>
                    <span class="text-[10px] font-semibold {{ $homeActive ? 'text-black' : 'text-gray-400' }}">Home</span>
                </a>

                {{-- Fund --}}
                @php $fundActive = request()->routeIs('wallet.fund'); @endphp
                <a href="{{ route('wallet.fund') }}"
                   class="flex flex-col items-center gap-1 transition active:scale-95">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl transition
                        {{ $fundActive ? 'bg-black text-white' : 'text-gray-400 hover:bg-gray-100' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="{{ $fundActive ? '2.2' : '1.8' }}" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                    </span>
                    <span class="text-[10px] font-semibold {{ $fundActive ? 'text-black' : 'text-gray-400' }}">Fund</span>
                </a>

                {{-- Send --}}
                @php $sendActive = request()->routeIs('wallet.send'); @endphp
                <a href="{{ route('wallet.send') }}"
                   class="flex flex-col items-center gap-1 transition active:scale-95">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl transition
                        {{ $sendActive ? 'bg-black text-white' : 'text-gray-400 hover:bg-gray-100' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="{{ $sendActive ? '2.2' : '1.8' }}" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-7-7 7 7-7 7"/>
                        </svg>
                    </span>
                    <span class="text-[10px] font-semibold {{ $sendActive ? 'text-black' : 'text-gray-400' }}">Send</span>
                </a>

                {{-- History --}}
                @php $histActive = request()->routeIs('wallet.history'); @endphp
                <a href="{{ route('wallet.history') }}"
                   class="flex flex-col items-center gap-1 transition active:scale-95">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl transition
                        {{ $histActive ? 'bg-black text-white' : 'text-gray-400 hover:bg-gray-100' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="{{ $histActive ? '2.2' : '1.8' }}" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <span class="text-[10px] font-semibold {{ $histActive ? 'text-black' : 'text-gray-400' }}">History</span>
                </a>

                {{-- Profile --}}
                @php $profActive = request()->routeIs('profile'); @endphp
                <a href="{{ route('profile') }}"
                   class="flex flex-col items-center gap-1 transition active:scale-95">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl transition
                        {{ $profActive ? 'bg-black text-white' : 'text-gray-400 hover:bg-gray-100' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="{{ $profActive ? '2.2' : '1.8' }}" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </span>
                    <span class="text-[10px] font-semibold {{ $profActive ? 'text-black' : 'text-gray-400' }}">Profile</span>
                </a>

            </div>
        </nav>
    </div>

</body>
</html>
