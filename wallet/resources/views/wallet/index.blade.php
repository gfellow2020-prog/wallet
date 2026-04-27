<x-layouts.mobile title="ExtraCash Wallet">
    <x-mobile.wallet-header
        :user-name="$user?->name ?? 'User'"
        :currency="$currencyCode"
        :currency-symbol="$currencySymbol"
        :balance="$wallet?->balance ?? 0"
        :card-number="$maskedCardNumber"
        :expiry="$expiry"
    />

    <x-mobile.action-grid />

    <x-mobile.transaction-list :transactions="$transactions" />

    {{-- Bottom spacer for bottom nav --}}
    <div class="h-4"></div>
</x-layouts.mobile>
