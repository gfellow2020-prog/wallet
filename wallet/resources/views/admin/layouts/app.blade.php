<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ExtraCash Admin')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    borderRadius: {
                        DEFAULT: '0.25rem',
                        'sm': '0.125rem',
                        'md': '0.25rem',
                        'lg': '0.375rem',
                        'xl': '0.5rem',
                    },
                }
            }
        }
    </script>
    <style>
        #sidebar { transition: transform 0.3s ease; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #d4d4d4; border-radius: 9999px; }
        .dark ::-webkit-scrollbar-thumb { background: #404040; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

        .badge { display:inline-flex;align-items:center;padding:2px 10px;border-radius:4px;font-size:11px;font-weight:600; }
        .badge-verified   { background:#171717;color:#fff; }
        .badge-pending    { background:#525252;color:#fff; }
        .badge-rejected   { background:#e5e5e5;color:#404040;border:1px solid #d4d4d4; }
        .badge-successful { background:#171717;color:#fff; }
        .badge-failed     { background:#e5e5e5;color:#404040;border:1px solid #d4d4d4; }
        .badge-processing { background:#525252;color:#fff; }
        .badge-requested  { background:#f5f5f5;color:#525252;border:1px solid #e5e5e5; }
        .badge-approved   { background:#262626;color:#fff; }
        .badge-paid       { background:#171717;color:#fff; }

        .btn-primary   { display:inline-flex;align-items:center;gap:6px;padding:8px 18px;font-size:13px;font-weight:600;background:#171717;color:#fff;border-radius:4px;border:none;cursor:pointer;transition:all .15s; }
        .btn-primary:hover { background:#404040; }
        .btn-secondary { display:inline-flex;align-items:center;gap:6px;padding:8px 18px;font-size:13px;font-weight:600;background:#f5f5f5;color:#171717;border-radius:4px;border:1px solid #d4d4d4;cursor:pointer;transition:all .15s; }
        .btn-secondary:hover { background:#e5e5e5; }
        .btn-danger    { display:inline-flex;align-items:center;gap:6px;padding:8px 18px;font-size:13px;font-weight:600;background:#fff;color:#171717;border-radius:4px;border:1.5px solid #404040;cursor:pointer;transition:all .15s; }
        .btn-danger:hover { background:#171717;color:#fff; }

        .card { background:#fff;border:1px solid #e5e5e5;border-radius:4px; }
        .dark .card { background:#171717;border-color:#404040; }

        input[type="text"],input[type="email"],input[type="password"],input[type="search"],input[type="number"],input[type="tel"],input[type="date"],input[type="url"],select,textarea {
            border-color:#d4d4d4 !important; border-radius:4px !important;
        }
        input:focus,select:focus,textarea:focus {
            border-color:#171717 !important; outline:none !important;
            box-shadow:0 0 0 2px rgba(23,23,23,0.12) !important;
        }
        th { color:#525252 !important; font-size:11px !important; text-transform:uppercase; letter-spacing:.05em; }
        .dark th { color:#737373 !important; }
    </style>
    @stack('styles')
</head>
<body class="bg-neutral-50 dark:bg-neutral-950 text-neutral-800 dark:text-neutral-100 antialiased">

<div class="flex h-screen overflow-hidden">

    {{-- ===== SIDEBAR ===== --}}
    <aside id="sidebar"
           class="fixed lg:static inset-y-0 left-0 z-40
                  w-64 flex flex-col flex-shrink-0
                  bg-neutral-950 dark:bg-neutral-950
                  border-r border-neutral-800
                  -translate-x-full lg:translate-x-0
                  overflow-hidden">

        {{-- Logo --}}
        <div class="h-16 flex items-center justify-between px-4 border-b border-neutral-800 flex-shrink-0">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 min-w-0 text-left rounded hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-white/20 -m-1 p-1">
                <div class="w-8 h-8 bg-white rounded flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-neutral-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-white font-bold text-sm leading-tight">ExtraCash</p>
                    <p class="text-neutral-500 text-xs">Admin Portal</p>
                </div>
            </a>
            <button onclick="closeSidebar()" class="lg:hidden text-neutral-400 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-0.5 scrollbar-hide">
            @php
                $navGroups = [
                    'Overview' => [
                        ['route'=>'admin.dashboard',    'label'=>'Dashboard',        'icon'=>'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'active'=>['admin.dashboard']],
                    ],
                    'Users & KYC' => [
                        ['route'=>'admin.users',        'label'=>'Users',            'icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'active'=>['admin.users*']],
                        ['route'=>'admin.kyc',          'label'=>'KYC Reviews',      'icon'=>'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'active'=>['admin.kyc*']],
                    ],
                    'Finance' => [
                        ['route'=>'admin.payments',     'label'=>'Payments',         'icon'=>'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'active'=>['admin.payments']],
                        ['route'=>'admin.cashbacks',    'label'=>'Cashback',         'icon'=>'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'active'=>['admin.cashbacks']],
                        ['route'=>'admin.withdrawals',  'label'=>'Withdrawals',      'icon'=>'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'active'=>['admin.withdrawals*']],
                        ['route'=>'admin.wallets',      'label'=>'Wallets',          'icon'=>'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4', 'active'=>['admin.wallets*']],
                        ['route'=>'admin.finance.marketplace',    'label'=>'Marketplace finance', 'icon'=>'M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6m16 0h-3.5a1.5 1.5 0 01-3 0H10.5a1.5 1.5 0 01-3 0H4', 'active'=>['admin.finance.marketplace*']],
                        ['route'=>'admin.finance.settlements',    'label'=>'Merchant settlements', 'icon'=>'M3 3h18v6H3V3zm0 12h18v6H3v-6zm2-7h2v2H5V8zm0 12h2v2H5v-2z', 'active'=>['admin.finance.settlements*']],
                        ['route'=>'admin.finance.revenue',        'label'=>'Fees & revenue', 'icon'=>'M9 17v-2m3 2v-4m3 4v-6M7 3h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z', 'active'=>['admin.finance.revenue*']],
                        ['route'=>'admin.finance.reconciliation', 'label'=>'Reconciliation', 'icon'=>'M9 12l2 2 4-4m5 0a9 9 0 11-18 0 9 9 0 0118 0z', 'active'=>['admin.finance.reconciliation*']],
                    ],
                    'Pay & marketplace' => [
                        ['route'=>'admin.merchants',    'label'=>'Partner merchants', 'icon'=>'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'active'=>['admin.merchants*']],
                        ['route'=>'admin.categories.index', 'label'=>'Categories', 'icon'=>'M4 6h16M4 12h16M4 18h16', 'active'=>['admin.categories*']],
                        ['route'=>'admin.orders',       'label'=>'Marketplace orders', 'icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', 'active'=>['admin.orders*']],
                    ],
                    'Risk' => [
                        ['route'=>'admin.fraud',        'label'=>'Fraud Flags',      'icon'=>'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z', 'active'=>['admin.fraud*']],
                        ['route'=>'admin.adjustments',  'label'=>'Adjustments',      'icon'=>'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'active'=>['admin.adjustments*']],
                    ],
                    'System' => [
                        ['route'=>'admin.audit',        'label'=>'Audit Logs',       'icon'=>'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'active'=>['admin.audit*']],
                    ],
                ];

                if (auth()->user()?->can('roles.view') || auth()->user()?->can('permissions.view')) {
                    $navGroups['Access control'] = array_values(array_filter([
                        auth()->user()?->can('roles.view') ? ['route'=>'admin.roles.index', 'label'=>'Roles', 'icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'active'=>['admin.roles*']] : null,
                        auth()->user()?->can('permissions.view') ? ['route'=>'admin.permissions.index', 'label'=>'Permissions', 'icon'=>'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'active'=>['admin.permissions*']] : null,
                    ]));
                }
            @endphp

            @foreach($navGroups as $groupLabel => $items)
                <p class="text-xs font-semibold uppercase tracking-widest text-neutral-600 px-3 pt-5 pb-1.5">{{ $groupLabel }}</p>
                @foreach($items as $item)
                    @php $active = request()->routeIs(...$item['active']); @endphp
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded text-sm font-medium transition group
                              {{ $active ? 'bg-white text-neutral-900' : 'text-neutral-400 hover:bg-neutral-800 hover:text-white' }}">
                        <svg class="w-4 h-4 flex-shrink-0 {{ $active ? 'text-neutral-900' : 'text-neutral-500 group-hover:text-white' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $item['icon'] }}"/>
                        </svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>

        @php $settingsNavActive = request()->routeIs('admin.settings*'); @endphp
        <div class="flex-shrink-0 border-t border-neutral-800 px-3 pt-3 pb-2">
            <a href="{{ route('admin.settings') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded text-sm font-medium transition group
                      {{ $settingsNavActive ? 'bg-white text-neutral-900' : 'text-neutral-400 hover:bg-neutral-800 hover:text-white' }}">
                <svg class="w-4 h-4 flex-shrink-0 {{ $settingsNavActive ? 'text-neutral-900' : 'text-neutral-500 group-hover:text-white' }}"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span>Settings</span>
            </a>
        </div>

        {{-- Bottom user --}}
        <div class="border-t border-neutral-800 p-4 flex-shrink-0">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.profile.show') }}"
                   class="flex items-center gap-3 flex-1 min-w-0 rounded p-1 -m-1 hover:bg-neutral-900/40 transition"
                   title="My Profile">
                    <div class="w-8 h-8 rounded bg-neutral-700 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                        {{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 2)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-sm font-semibold truncate">{{ auth()->user()?->name ?? 'Admin' }}</p>
                        <p class="text-neutral-500 text-xs truncate">{{ auth()->user()?->email ?? '' }}</p>
                    </div>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Sign out"
                            class="text-neutral-500 hover:text-white transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ===== MAIN PANEL ===== --}}
    <div class="flex flex-col flex-1 overflow-hidden">

        {{-- NAVBAR --}}
        <header class="sticky top-0 z-30 h-16 flex items-center justify-between gap-4 px-4 md:px-6
                       bg-white dark:bg-neutral-950 border-b border-neutral-200 dark:border-neutral-800">
            <div class="flex items-center gap-3">
                <button onclick="openSidebar()" class="lg:hidden p-2 rounded text-neutral-500 hover:bg-neutral-100 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div>
                    <h1 class="font-bold text-neutral-900 dark:text-white text-sm">@yield('page-title', 'Dashboard')</h1>
                    @hasSection('breadcrumb')
                        <p class="text-xs text-neutral-500">@yield('breadcrumb')</p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2">
                {{-- Notifications --}}
                <button class="relative p-2 rounded text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </button>

                {{-- Theme toggle --}}
                <button onclick="toggleTheme()" class="p-2 rounded text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition">
                    <svg id="theme-icon-sun" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/>
                    </svg>
                    <svg id="theme-icon-moon" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>

                {{-- Avatar (clickable) --}}
                <a href="{{ route('admin.profile.show') }}" title="My Profile"
                   class="w-8 h-8 rounded bg-neutral-900 dark:bg-white flex items-center justify-center text-white dark:text-neutral-900 text-xs font-bold hover:opacity-90 transition">
                    {{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 2)) }}
                </a>
            </div>
        </header>

        {{-- CONTENT --}}
        <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
            @if(session('success'))
                <div class="mb-5 flex items-center gap-3 px-4 py-3 rounded border border-neutral-300 bg-white text-neutral-700 text-sm dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-300">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-5 flex items-center gap-3 px-4 py-3 rounded border border-neutral-400 bg-neutral-100 text-neutral-800 text-sm dark:bg-neutral-900 dark:border-neutral-700">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<div id="sidebar-overlay" onclick="closeSidebar()"
     class="fixed inset-0 bg-black/50 z-20 hidden lg:hidden"></div>

<script>
    (function () {
        const saved = localStorage.getItem('ec-admin-theme') || 'light';
        document.documentElement.classList.toggle('dark', saved === 'dark');
    })();

    function toggleTheme() {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('ec-admin-theme', isDark ? 'dark' : 'light');
        document.getElementById('theme-icon-sun').classList.toggle('hidden', isDark);
        document.getElementById('theme-icon-moon').classList.toggle('hidden', !isDark);
    }
    function openSidebar() {
        document.getElementById('sidebar').classList.remove('-translate-x-full');
        document.getElementById('sidebar-overlay').classList.remove('hidden');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.add('-translate-x-full');
        document.getElementById('sidebar-overlay').classList.add('hidden');
    }
    document.addEventListener('DOMContentLoaded', function () {
        const isDark = document.documentElement.classList.contains('dark');
        document.getElementById('theme-icon-sun')?.classList.toggle('hidden', isDark);
        document.getElementById('theme-icon-moon')?.classList.toggle('hidden', !isDark);
    });
</script>

{{-- Global spinner --}}
<div id="global-spinner" class="fixed inset-0 z-[9999] items-center justify-center bg-white/60 dark:bg-neutral-950/60 backdrop-blur-sm" style="display:none">
    <div class="flex flex-col items-center gap-3">
        <svg class="animate-spin h-10 w-10 text-neutral-900 dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Loading…</span>
    </div>
</div>
<script>
(function(){
    var s = document.getElementById('global-spinner');
    document.addEventListener('submit', function(e){ if(e.target.tagName==='FORM') s.style.display='flex'; });
    document.addEventListener('click', function(e){
        var a = e.target.closest('a[href]');
        if(!a) return;
        var h = a.getAttribute('href')||'';
        if(h.startsWith('#')||h.startsWith('javascript')||a.target==='_blank') return;
        s.style.display='flex';
    });
    window.addEventListener('pageshow', function(){ s.style.display='none'; });
})();
</script>

@stack('scripts')
</body>
</html>
