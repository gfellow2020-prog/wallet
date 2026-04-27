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
<body class="min-h-screen bg-white antialiased font-sans">

    {{-- Fixed top-left logo --}}
    <header class="fixed left-0 top-0 z-50 flex items-center gap-2.5 px-6 py-5">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-black text-white text-base font-black tracking-tight select-none">G</span>
        <span class="text-xl font-extrabold tracking-tight text-black">ExtraCash</span>
    </header>

    {{-- Full-screen flex wrapper — centres the card vertically --}}
    <div class="flex min-h-screen w-full items-center justify-center px-6 py-12">

        {{-- Card --}}
        <div class="w-full max-w-md">

        <div>
            <div class="mb-10">

            {{-- Flash: status (success/info) --}}
            @if (session('status'))
                <div class="mb-5 flex items-start gap-3 rounded-lg border border-gray-100 bg-gray-50 px-4 py-3.5">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-black" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    <p class="text-[13px] font-medium text-gray-800">{{ session('status') }}</p>
                </div>
            @endif

            {{-- Flash: validation errors --}}
            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-gray-100 bg-gray-50 px-4 py-3.5">
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)
                            <li class="flex items-start gap-2 text-[13px] text-gray-800">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-black" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-.75-5a.75.75 0 001.5 0V9a.75.75 0 00-1.5 0v4zm.75-6.5a.75.75 0 100 1.5.75.75 0 000-1.5z" clip-rule="evenodd"/>
                                </svg>
                                {{ $error }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Page content --}}
            {{ $slot }}
        </div>

        {{-- Footer --}}
        <p class="text-center text-[11px] text-gray-300 mt-10">
            &copy; {{ date('Y') }} ExtraCash. All rights reserved.
        </p>

        </div>{{-- /card --}}
    </div>{{-- /full-screen wrapper --}}

</body>
</html>
