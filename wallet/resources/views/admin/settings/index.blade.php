@extends('admin.layouts.app')
@section('title', 'Settings — ExtraCash Admin')
@section('page-title', 'Settings')
@section('breadcrumb', 'Platform configuration')

@section('content')

<form method="POST" action="{{ route('admin.settings.update') }}" id="settings-form">
    @csrf

    <div class="card overflow-hidden">
        {{-- Tab bar --}}
        <div class="border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50/80 dark:bg-neutral-900/50">
            <nav class="flex flex-wrap gap-0 px-2 sm:px-4" role="tablist" aria-label="Settings sections">
                <button type="button" role="tab" id="tab-btn-lenco" data-panel="lenco" aria-selected="true"
                        class="settings-tab-btn is-active relative flex items-center gap-2 px-3 sm:px-4 py-3.5 text-sm font-semibold border-b-2 border-neutral-900 dark:border-white text-neutral-900 dark:text-white transition-colors -mb-px">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    <span class="whitespace-nowrap">Lenco</span>
                </button>
                <button type="button" role="tab" id="tab-btn-cashback" data-panel="cashback" aria-selected="false"
                        class="settings-tab-btn relative flex items-center gap-2 px-3 sm:px-4 py-3.5 text-sm font-semibold border-b-2 border-transparent text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white transition-colors -mb-px">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="whitespace-nowrap">Cashback</span>
                </button>
                <button type="button" role="tab" id="tab-btn-withdrawals" data-panel="withdrawals" aria-selected="false"
                        class="settings-tab-btn relative flex items-center gap-2 px-3 sm:px-4 py-3.5 text-sm font-semibold border-b-2 border-transparent text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white transition-colors -mb-px">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span class="whitespace-nowrap">Withdrawals</span>
                </button>
                <button type="button" role="tab" id="tab-btn-compliance" data-panel="compliance" aria-selected="false"
                        class="settings-tab-btn relative flex items-center gap-2 px-3 sm:px-4 py-3.5 text-sm font-semibold border-b-2 border-transparent text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white transition-colors -mb-px">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span class="whitespace-nowrap">Compliance</span>
                </button>
                <button type="button" role="tab" id="tab-btn-sms" data-panel="sms" aria-selected="false"
                        class="settings-tab-btn relative flex items-center gap-2 px-3 sm:px-4 py-3.5 text-sm font-semibold border-b-2 border-transparent text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white transition-colors -mb-px">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h6m-6 4h10M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="whitespace-nowrap">SMS (OTP)</span>
                </button>
            </nav>
        </div>

        <div class="px-4 sm:px-6 py-6 min-h-[320px]">

            {{-- ===== PANEL: Lenco ===== --}}
            <div id="panel-lenco" class="settings-tab-panel" role="tabpanel" aria-labelledby="tab-btn-lenco">
                @php
                    $lencoApiSaved = !empty($settings['lenco_api_key'] ?? '');
                    $lencoSecretSaved = !empty($settings['lenco_secret_key'] ?? '');
                    $lencoMask = function (?string $v): string {
                        if ($v === null || $v === '') {
                            return '';
                        }
                        $v = (string) $v;
                        if (strlen($v) <= 12) {
                            return str_repeat('•', min(12, strlen($v)));
                        }

                        return substr($v, 0, 8).'••••••••'.substr($v, -4);
                    };
                @endphp
                <div class="mb-5">
                    <h2 class="font-bold text-neutral-900 dark:text-white text-base">Payment Gateway — Lenco (Access API)</h2>
                    <p class="text-xs text-neutral-500 mt-1">Values below map to the <strong class="text-neutral-600 dark:text-neutral-400">Lenco developer portal</strong> (Access v2). The portal’s <strong>API Name</strong> is only a label there — it is <strong>not</strong> required in this form.</p>
                    <p class="text-xs text-neutral-500 mt-1.5">Credentials are stored in the application database. Restrict database access and use strong secrets in production.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-2 uppercase tracking-wide">Environment</label>
                        <p class="text-xs text-neutral-500 mb-2">Match the key set in the Lenco portal (Live vs sandbox / test).</p>
                        <div class="flex gap-3">
                            <label class="flex items-center gap-2.5 cursor-pointer px-4 py-2.5 rounded border transition lenco-env-label
                                          {{ ($settings['lenco_environment'] ?? 'sandbox') === 'sandbox'
                                             ? 'bg-neutral-900 border-neutral-900 text-white'
                                             : 'bg-white border-neutral-300 text-neutral-600 hover:border-neutral-400 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-300' }}">
                                <input type="radio" name="settings[lenco_environment]" value="sandbox"
                                       class="sr-only"
                                       {{ ($settings['lenco_environment'] ?? 'sandbox') === 'sandbox' ? 'checked' : '' }}>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                </svg>
                                <span class="text-sm font-semibold">Sandbox</span>
                            </label>
                            <label class="flex items-center gap-2.5 cursor-pointer px-4 py-2.5 rounded border transition lenco-env-label
                                          {{ ($settings['lenco_environment'] ?? 'sandbox') === 'live'
                                             ? 'bg-neutral-900 border-neutral-900 text-white'
                                             : 'bg-white border-neutral-300 text-neutral-600 hover:border-neutral-400 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-300' }}">
                                <input type="radio" name="settings[lenco_environment]" value="live"
                                       class="sr-only"
                                       {{ ($settings['lenco_environment'] ?? 'sandbox') === 'live' ? 'checked' : '' }}>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                                <span class="text-sm font-semibold">Live</span>
                            </label>
                        </div>
                        @error('settings.lenco_environment')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2 pt-1 border-t border-neutral-200 dark:border-neutral-800">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Lenco · API Keys tab</p>
                        <p class="text-xs text-neutral-500 mt-1">Copy <strong>Base URL</strong>, <strong>Public key</strong>, and <strong>API (Secret) key</strong> from the portal. Lenco v2 authorizes the <strong>secret</strong> as <code class="text-xs">Authorization: Bearer</code>; the public key is sent as <code class="text-xs">X-Secret-Key</code> when both are set, plus <code class="text-xs">X-Environment</code> (must match the key: Sandbox vs Live).</p>
                    </div>

                    <div class="md:col-span-2">
                        <label for="lenco_base_url" class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 uppercase tracking-wide">Base URL</label>
                        <p class="text-xs text-neutral-500 mb-1.5">Portal field: <strong>Base URL</strong> (e.g. <code class="text-xs">https://api.lenco.co/access/v2</code>)</p>
                        <input type="url" id="lenco_base_url" name="settings[lenco_base_url]"
                               value="{{ old('settings.lenco_base_url', $settings['lenco_base_url'] ?? 'https://api.lenco.co/access/v2') }}"
                               class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white font-mono">
                    </div>

                    <div>
                        <label for="lenco_api_key" class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">Public key</label>
                        <p class="text-xs text-neutral-500 mb-1.5">Lenco: <strong>Public Key</strong> (often <code class="text-xs">pub-…</code>) — sent as <code class="text-xs">X-Secret-Key</code> with the secret in <code class="text-xs">Authorization: Bearer</code></p>
                        @if($lencoApiSaved)
                            <p class="text-xs text-neutral-500 mb-1.5">Current: <span class="font-mono text-neutral-700 dark:text-neutral-200">{{ $lencoMask($settings['lenco_api_key'] ?? '') }}</span> — leave blank to keep the saved value.</p>
                        @endif
                        <input type="text" id="lenco_api_key" name="settings[lenco_api_key]"
                               value="{{ old('settings.lenco_api_key', $lencoApiSaved ? '' : ($settings['lenco_api_key'] ?? '')) }}"
                               placeholder="{{ $lencoApiSaved ? 'Paste a new public key to replace' : 'pub-… (paste from Lenco)' }}"
                               autocomplete="off"
                               class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white font-mono">
                    </div>

                    <div>
                        <label for="lenco_secret_key" class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">API (Secret) key</label>
                        <p class="text-xs text-neutral-500 mb-1.5">Lenco: <strong>API (Secret) key</strong> (copy from portal; shown once) — required for <code class="text-xs">Authorization: Bearer</code> (avoids 401 Unauthorized)</p>
                        @if($lencoSecretSaved)
                            <p class="text-xs text-neutral-500 mb-1.5">Current: <span class="font-mono text-neutral-700 dark:text-neutral-200">{{ $lencoMask($settings['lenco_secret_key'] ?? '') }}</span> — leave blank to keep the saved value.</p>
                        @endif
                        <div class="relative">
                            <input type="password" id="lenco_secret_key" name="settings[lenco_secret_key]"
                                   value="{{ old('settings.lenco_secret_key', '') }}"
                                   placeholder="{{ $lencoSecretSaved ? 'Paste a new API (Secret) key to replace' : 'Paste the long secret from Lenco' }}"
                                   autocomplete="off"
                                   class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white font-mono pr-10">
                            <button type="button" onclick="toggleVisibility('lenco_secret_key')"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="md:col-span-2 pt-1 border-t border-neutral-200 dark:border-neutral-800">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Payouts &amp; request locale</p>
                        <p class="text-xs text-neutral-500 mt-1">Not on the <strong>API Keys</strong> screen — required by our app for transfers and API payloads.</p>
                    </div>

                    <div>
                        <label for="lenco_country" class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 uppercase tracking-wide">Country (API)</label>
                        <p class="text-xs text-neutral-500 mb-1.5">ISO-2 code sent on collection/transfer calls (e.g. Zambia: <code class="text-xs">zm</code>)</p>
                        <input type="text" id="lenco_country" name="settings[lenco_country]" maxlength="2"
                               value="{{ old('settings.lenco_country', $settings['lenco_country'] ?? 'zm') }}"
                               placeholder="zm"
                               class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white font-mono uppercase"
                               style="text-transform: uppercase">
                    </div>

                    <div>
                        <label for="lenco_account_id" class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">Account to debit (payouts)</label>
                        <p class="text-xs text-neutral-500 mb-1.5">Lenco <strong>account / wallet</strong> UUID to debit for disbursements — from your Lenco dashboard, not the API Keys page.</p>
                        <input type="text" id="lenco_account_id" name="settings[lenco_account_id]"
                               value="{{ old('settings.lenco_account_id', $settings['lenco_account_id'] ?? '') }}"
                               placeholder="UUID (required for transfers / withdrawals)"
                               class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white font-mono"
                               autocomplete="off">
                    </div>

                    <div class="md:col-span-2 pt-1 border-t border-neutral-200 dark:border-neutral-800">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Lenco · Webhook tab (optional)</p>
                        <p class="text-xs text-neutral-500 mt-1">If the portal provides a signing secret for server webhooks, paste it here. Used when verifying callbacks to <code class="text-xs">/webhook/lenco/…</code>. Leave empty until you configure webhooks.</p>
                    </div>

                    <div class="md:col-span-2">
                        <label for="lenco_webhook_secret" class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5">Webhook signing secret</label>
                        <div class="relative max-w-lg">
                            <input type="password" id="lenco_webhook_secret" name="settings[lenco_webhook_secret]"
                                   value="{{ old('settings.lenco_webhook_secret', $settings['lenco_webhook_secret'] ?? '') }}"
                                   placeholder="From Lenco → Webhook (if applicable)"
                                   class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white font-mono pr-10"
                                   autocomplete="off">
                            <button type="button" onclick="toggleVisibility('lenco_webhook_secret')"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="md:col-span-2 flex items-center gap-3 p-3 rounded bg-neutral-50 dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
                        @if(!empty($settings['lenco_api_key']))
                            <span class="w-2 h-2 rounded-full bg-neutral-900 dark:bg-white flex-shrink-0"></span>
                            <p class="text-xs text-neutral-600 dark:text-neutral-400">Lenco credentials saved — <strong class="text-neutral-900 dark:text-white">{{ ucfirst($settings['lenco_environment'] ?? 'sandbox') }}</strong> mode</p>
                        @else
                            <span class="w-2 h-2 rounded-full bg-neutral-400 flex-shrink-0"></span>
                            <p class="text-xs text-neutral-500">Add <strong>Public key</strong> and <strong>API (Secret) key</strong> from the Lenco API Keys tab to enable Lenco in the app.</p>
                        @endif
                    </div>

                    <div class="md:col-span-2 space-y-3 p-4 rounded border border-neutral-200 dark:border-neutral-800 bg-neutral-50/80 dark:bg-neutral-900/40">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Callback and webhook URLs</h3>
                        <p class="text-xs text-neutral-500">Use these in the Lenco developer dashboard where applicable. <code class="text-xs">X-Callback-URL</code> is sent on outgoing collection/transfer API calls. Incoming POSTs to <code class="text-xs">/webhook/lenco/…</code> are accepted (CSRF-exempt) and logged.</p>
                        @foreach($lencoCallbackUrls as $lencoLabel => $lencoUrl)
                        <div class="flex flex-col sm:flex-row sm:items-start gap-2 sm:gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-neutral-700 dark:text-neutral-300">{{ $lencoLabel }}</p>
                                <p class="text-xs font-mono text-neutral-800 dark:text-neutral-200 break-all mt-0.5">{{ $lencoUrl }}</p>
                            </div>
                            <button type="button" class="lenco-copy-url btn-secondary text-xs py-1.5 px-2.5 shrink-0" data-url="{{ $lencoUrl }}">Copy</button>
                        </div>
                        @endforeach
                    </div>

                    <div class="md:col-span-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-1 border-t border-neutral-200 dark:border-neutral-800">
                        <p class="text-xs text-neutral-500">Test calls Lenco with a <strong>fake</strong> collection reference (like Landlord). It uses values <strong>already saved</strong> in the database — save changes before testing.</p>
                        <form method="POST" action="{{ route('admin.settings.lenco.test') }}" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-900 text-neutral-800 dark:text-white hover:border-neutral-900 dark:hover:border-white">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                Test Lenco connection
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ===== PANEL: Cashback ===== --}}
            <div id="panel-cashback" class="settings-tab-panel hidden" role="tabpanel" aria-labelledby="tab-btn-cashback" hidden>
                <div class="mb-5">
                    <h2 class="font-bold text-neutral-900 dark:text-white text-base">Cashback rules</h2>
                    <p class="text-xs text-neutral-500 mt-1">Global cashback rates, holds, and caps</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <div class="md:col-span-2 flex items-center justify-between p-4 rounded border border-neutral-200 dark:border-neutral-800">
                        <div>
                            <p class="text-sm font-semibold text-neutral-900 dark:text-white">Cashback enabled</p>
                            <p class="text-xs text-neutral-500 mt-0.5">Toggle cashback issuance globally</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="settings[cashback_enabled]" value="0">
                            <input type="checkbox" name="settings[cashback_enabled]" value="1"
                                   {{ ($settings['cashback_enabled'] ?? '1') == '1' ? 'checked' : '' }}
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-neutral-200 peer-checked:bg-neutral-900 rounded-full transition peer-focus:ring-2 peer-focus:ring-neutral-400 dark:bg-neutral-700 dark:peer-checked:bg-white"></div>
                            <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white dark:bg-neutral-900 rounded-full transition peer-checked:translate-x-5 shadow-sm"></div>
                        </label>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 uppercase tracking-wide">
                            Cashback rate <span class="normal-case font-normal text-neutral-400">(0.02 = 2%)</span>
                        </label>
                        <input type="number" name="settings[cashback_rate]" step="0.001" min="0" max="1"
                               value="{{ old('settings.cashback_rate', $settings['cashback_rate'] ?? '0.02') }}"
                               class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 uppercase tracking-wide">
                            Hold days <span class="normal-case font-normal text-neutral-400">(before release)</span>
                        </label>
                        <input type="number" name="settings[cashback_hold_days]" min="0" max="90"
                               value="{{ old('settings.cashback_hold_days', $settings['cashback_hold_days'] ?? '7') }}"
                               class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 uppercase tracking-wide">Daily cap (ZMW)</label>
                        <input type="number" name="settings[cashback_daily_cap]" step="0.01" min="0"
                               value="{{ old('settings.cashback_daily_cap', $settings['cashback_daily_cap'] ?? '500') }}"
                               class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 uppercase tracking-wide">Monthly cap (ZMW)</label>
                        <input type="number" name="settings[cashback_monthly_cap]" step="0.01" min="0"
                               value="{{ old('settings.cashback_monthly_cap', $settings['cashback_monthly_cap'] ?? '5000') }}"
                               class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
                    </div>
                </div>
            </div>

            {{-- ===== PANEL: Withdrawals ===== --}}
            <div id="panel-withdrawals" class="settings-tab-panel hidden" role="tabpanel" aria-labelledby="tab-btn-withdrawals" hidden>
                <div class="mb-5">
                    <h2 class="font-bold text-neutral-900 dark:text-white text-base">Withdrawal limits</h2>
                    <p class="text-xs text-neutral-500 mt-1">Minimum amounts, daily max, and KYC rules</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <div>
                        <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 uppercase tracking-wide">Minimum withdrawal (ZMW)</label>
                        <input type="number" name="settings[withdrawal_min_amount]" step="0.01" min="0"
                               value="{{ old('settings.withdrawal_min_amount', $settings['withdrawal_min_amount'] ?? '20') }}"
                               class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 uppercase tracking-wide">Daily max withdrawal (ZMW)</label>
                        <input type="number" name="settings[withdrawal_daily_max]" step="0.01" min="0"
                               value="{{ old('settings.withdrawal_daily_max', $settings['withdrawal_daily_max'] ?? '10000') }}"
                               class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white">
                    </div>

                    <div class="md:col-span-2 flex items-center justify-between p-4 rounded border border-neutral-200 dark:border-neutral-800">
                        <div>
                            <p class="text-sm font-semibold text-neutral-900 dark:text-white">KYC required for withdrawals</p>
                            <p class="text-xs text-neutral-500 mt-0.5">Users must pass KYC before withdrawing</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="settings[withdrawal_require_kyc]" value="0">
                            <input type="checkbox" name="settings[withdrawal_require_kyc]" value="1"
                                   {{ ($settings['withdrawal_require_kyc'] ?? '1') == '1' ? 'checked' : '' }}
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-neutral-200 peer-checked:bg-neutral-900 rounded-full transition peer-focus:ring-2 peer-focus:ring-neutral-400 dark:bg-neutral-700 dark:peer-checked:bg-white"></div>
                            <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white dark:bg-neutral-900 rounded-full transition peer-checked:translate-x-5 shadow-sm"></div>
                        </label>
                    </div>
                </div>
            </div>

            {{-- ===== PANEL: Compliance ===== --}}
            <div id="panel-compliance" class="settings-tab-panel hidden" role="tabpanel" aria-labelledby="tab-btn-compliance" hidden>
                <div class="mb-5">
                    <h2 class="font-bold text-neutral-900 dark:text-white text-base">NRC lookup (SmartData)</h2>
                    <p class="text-xs text-neutral-500 mt-1">Used during mobile registration and verification</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="smartdata_api_key" class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 uppercase tracking-wide">SmartData API key</label>
                        <div class="relative">
                            <input type="password" id="smartdata_api_key" name="settings[smartdata_api_key]"
                                   value="{{ old('settings.smartdata_api_key', $settings['smartdata_api_key'] ?? '') }}"
                                   placeholder="sk_..."
                                   class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white font-mono pr-10">
                            <button type="button" onclick="toggleVisibility('smartdata_api_key')"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="smartdata_base_url" class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 uppercase tracking-wide">Base URL</label>
                        <input type="url" id="smartdata_base_url" name="settings[smartdata_base_url]"
                               value="{{ old('settings.smartdata_base_url', $settings['smartdata_base_url'] ?? 'https://mysmartdata.tech/api/v1') }}"
                               class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white font-mono">
                    </div>
                </div>
            </div>

            {{-- ===== PANEL: SMS (OTP) ===== --}}
            <div id="panel-sms" class="settings-tab-panel hidden" role="tabpanel" aria-labelledby="tab-btn-sms" hidden>
                <div class="mb-5">
                    <h2 class="font-bold text-neutral-900 dark:text-white text-base">SMS provider (CloudServiceZM)</h2>
                    <p class="text-xs text-neutral-500 mt-1">Used for OTP delivery. If SMS fails or user has no phone number, we fall back to email.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2 flex items-center justify-between p-4 rounded border border-neutral-200 dark:border-neutral-800">
                        <div>
                            <p class="text-sm font-semibold text-neutral-900 dark:text-white">Enable SMS</p>
                            <p class="text-xs text-neutral-500 mt-0.5">Master switch for SMS sending</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="settings[sms_enabled]" value="0">
                            <input type="checkbox" name="settings[sms_enabled]" value="1"
                                   {{ ($settings['sms_enabled'] ?? '0') == '1' ? 'checked' : '' }}
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-neutral-200 peer-checked:bg-neutral-900 rounded-full transition peer-focus:ring-2 peer-focus:ring-neutral-400 dark:bg-neutral-700 dark:peer-checked:bg-white"></div>
                            <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white dark:bg-neutral-900 rounded-full transition peer-checked:translate-x-5 shadow-sm"></div>
                        </label>
                    </div>

                    <div>
                        <label for="sms_username" class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 uppercase tracking-wide">Username</label>
                        <input type="text" id="sms_username" name="settings[sms_username]"
                               value="{{ old('settings.sms_username', $settings['sms_username'] ?? '') }}"
                               class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white font-mono"
                               autocomplete="off">
                    </div>

                    <div>
                        <label for="sms_password" class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 uppercase tracking-wide">Password</label>
                        <div class="relative">
                            <input type="password" id="sms_password" name="settings[sms_password]"
                                   value="{{ old('settings.sms_password', '') }}"
                                   placeholder="{{ !empty($settings['sms_password'] ?? '') ? 'Leave blank to keep saved value' : '' }}"
                                   class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white font-mono pr-10"
                                   autocomplete="off">
                            <button type="button" onclick="toggleVisibility('sms_password')"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="sms_sender_id" class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 uppercase tracking-wide">Sender ID</label>
                        <input type="text" id="sms_sender_id" name="settings[sms_sender_id]"
                               value="{{ old('settings.sms_sender_id', $settings['sms_sender_id'] ?? 'Extracash') }}"
                               class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white font-mono"
                               autocomplete="off">
                    </div>

                    <div>
                        <label for="sms_short_code" class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300 mb-1.5 uppercase tracking-wide">Short code</label>
                        <input type="text" id="sms_short_code" name="settings[sms_short_code]"
                               value="{{ old('settings.sms_short_code', $settings['sms_short_code'] ?? '388') }}"
                               class="w-full px-3 py-2.5 text-sm border rounded bg-white dark:bg-neutral-900 dark:text-white font-mono"
                               autocomplete="off">
                    </div>
                </div>
            </div>

        </div>

        <div class="border-t border-neutral-200 dark:border-neutral-800 px-4 sm:px-6 py-4 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 bg-neutral-50/50 dark:bg-neutral-900/30">
            <p class="text-xs text-neutral-500 sm:max-w-md">Saves <strong>all</strong> sections in one go — switch tabs to review each area before saving.</p>
            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.settings') }}" class="btn-secondary">Reset view</a>
                <button type="submit" class="btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save all settings
                </button>
            </div>
        </div>
    </div>
</form>

@endsection

@push('scripts')
<script>
function toggleVisibility(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.type = el.type === 'password' ? 'text' : 'password';
}

(function () {
    function setTab(name) {
        var panels = document.querySelectorAll('.settings-tab-panel');
        var buttons = document.querySelectorAll('.settings-tab-btn');
        panels.forEach(function (p) {
            var isMatch = p.id === 'panel-' + name;
            p.classList.toggle('hidden', !isMatch);
            p.hidden = !isMatch;
        });
        buttons.forEach(function (b) {
            var on = b.getAttribute('data-panel') === name;
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            b.classList.remove('is-active', 'text-neutral-900', 'dark:text-white', 'border-neutral-900', 'dark:border-white', 'text-neutral-600', 'dark:text-neutral-400', 'border-transparent');
            if (on) {
                b.classList.add('is-active', 'text-neutral-900', 'dark:text-white', 'border-neutral-900', 'dark:border-white');
            } else {
                b.classList.add('text-neutral-600', 'dark:text-neutral-400', 'border-transparent');
            }
        });
        if (history.replaceState) {
            try { history.replaceState(null, '', '#settings-' + name); } catch (e) {}
        }
    }

    document.querySelectorAll('.settings-tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setTab(btn.getAttribute('data-panel'));
        });
    });

    // Lenco environment radio label styling
    function syncLencoEnvLabels() {
        document.querySelectorAll('input[name="settings[lenco_environment]"]').forEach(function (r) {
            var label = r.closest('label.lenco-env-label');
            if (!label) return;
            if (r.checked) {
                label.classList.add('bg-neutral-900', 'border-neutral-900', 'text-white');
                label.classList.remove('bg-white', 'border-neutral-300', 'text-neutral-600', 'dark:text-neutral-300');
            } else {
                label.classList.remove('bg-neutral-900', 'border-neutral-900', 'text-white');
                label.classList.add('bg-white', 'border-neutral-300', 'text-neutral-600', 'dark:bg-neutral-900', 'dark:border-neutral-700', 'dark:text-neutral-300');
            }
        });
    }
    document.querySelectorAll('input[name="settings[lenco_environment]"]').forEach(function (r) {
        r.addEventListener('change', syncLencoEnvLabels);
    });

    // Initial tab: hash #settings-cashback etc.
    var map = { lenco: 1, cashback: 1, withdrawals: 1, compliance: 1, sms: 1 };
    var start = 'lenco';
    var h = (window.location.hash || '').replace(/^#/, '');
    if (h.indexOf('settings-') === 0) {
        var key = h.replace('settings-', '');
        if (map[key]) start = key;
    }
    setTab(start);

    document.querySelectorAll('.lenco-copy-url').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-url');
            if (!url) return;
            function ok() {
                var prev = btn.textContent;
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = prev; }, 2000);
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(ok).catch(function () { window.prompt('Copy', url); });
            } else {
                window.prompt('Copy', url);
            }
        });
    });
})();
</script>
@endpush
