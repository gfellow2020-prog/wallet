<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'ExtraCash Wallet' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="mx-auto min-h-screen w-full max-w-md bg-white shadow-lg">
        <main class="px-4 pb-6 pt-4">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
