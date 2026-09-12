@extends('layouts.app')
@section('title', 'Buy / Sell USDT — Freedom Data')
@section('content')
<div class="mx-auto max-w-lg space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold">Buy / Sell USDT</h1>
        @if($rate->is_enabled)
            <p class="mt-1 text-sm text-ink-muted">
                @if($rate->buy_enabled)Buy rate <span class="font-mono font-semibold">₵{{ $rate->buy_rate }}</span>@endif
                @if($rate->buy_enabled && $rate->sell_enabled) · @endif
                @if($rate->sell_enabled)Sell rate <span class="font-mono font-semibold">₵{{ $rate->sell_rate }}</span>@endif
                per USDT
            </p>
        @else
            <div class="mt-4 card p-8 text-center">
                <p class="font-display font-semibold">USDT desk unavailable</p>
                <p class="mt-1 text-sm text-ink-muted">Check back later.</p>
            </div>
        @endif
    </div>

    @if($rate->is_enabled && ($rate->buy_enabled || $rate->sell_enabled))
    {{-- Tabs --}}
    @if($rate->buy_enabled && $rate->sell_enabled)
    <div class="flex gap-1 rounded-xl bg-paper-dim p-1">
        <button type="button" onclick="switchTab('BUY')" id="tab-BUY" class="flex-1 rounded-lg py-2.5 text-sm font-semibold bg-surface shadow-sm">Buy USDT</button>
        <button type="button" onclick="switchTab('SELL')" id="tab-SELL" class="flex-1 rounded-lg py-2.5 text-sm font-semibold text-ink-muted">Sell USDT</button>
    </div>
    @endif

    <form method="POST" action="/crypto/order" class="card space-y-4 p-6">
        @csrf
        <input type="hidden" name="type" id="crypto_type" value="{{ $rate->buy_enabled ? 'BUY' : 'SELL' }}">
        <input type="hidden" id="active_rate" value="{{ $rate->buy_enabled ? $rate->buy_rate : $rate->sell_rate }}">

        {{-- Amount --}}
        <div>
            <label class="mb-1 block text-sm font-medium" id="ghs_label">
                {{ $rate->buy_enabled ? 'Amount to pay (GHS)' : "Amount you'll receive (GHS)" }}
            </label>
            <input type="number" name="ghs_amount" id="ghs_amount" min="{{ $rate->min_ghs }}" max="{{ $rate->max_ghs }}" step="0.01"
                   placeholder="Between ₵{{ $rate->min_ghs }} and ₵{{ $rate->max_ghs }}" class="form-input font-mono" required value="{{ old('ghs_amount') }}">
            <div class="mt-2 flex items-center gap-2 rounded-lg bg-paper-dim px-3 py-2">
                <span class="text-sm text-ink-muted">You get</span>
                <span class="font-mono text-lg font-semibold text-gold-dark" id="usdt_equiv">0.0000</span>
                <span class="text-sm font-semibold text-ink-muted">USDT</span>
            </div>
        </div>

        {{-- Network --}}
        <div>
            <label class="mb-1 block text-sm font-medium">Wallet network</label>
            <select name="wallet_network" class="form-input">
                <option value="TRC20">TRC20 (Tron) — Recommended</option>
                <option value="BEP20">BEP20 (BNB Chain)</option>
                <option value="ERC20">ERC20 (Ethereum)</option>
            </select>
        </div>

        {{-- BUY fields --}}
        @if($rate->buy_enabled)
        <div id="buy-fields">
            <label class="mb-1 block text-sm font-medium">Your USDT wallet address</label>
            <input type="text" name="wallet_address" placeholder="Paste your wallet address" class="form-input font-mono text-sm" value="{{ old('wallet_address') }}">
            <p class="mt-1 text-xs text-ink-faint">We will send USDT to this address after your payment is confirmed.</p>
        </div>
        @endif

        {{-- SELL fields --}}
        @if($rate->sell_enabled)
        <div id="sell-fields" class="{{ $rate->buy_enabled ? 'hidden' : '' }} space-y-3">
            <div class="rounded-xl bg-paper-dim p-4 text-sm">
                <div class="text-ink-faint">Send USDT to this address:</div>
                <div class="mt-1.5 break-all rounded-lg bg-surface border border-border p-3 font-mono text-sm select-all">{{ $rate->wallet_address ?? 'Not set yet' }}</div>
                <p class="mt-2 text-xs text-ink-faint">Send on the correct network selected above.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Transaction hash (proof of payment)</label>
                <input type="text" name="tx_hash" placeholder="Paste tx hash after sending" class="form-input font-mono text-sm" value="{{ old('tx_hash') }}">
            </div>
        </div>
        @endif

        <button type="submit" class="btn-gold w-full py-3 text-base" id="crypto-submit" {{ auth()->check() ? '' : 'disabled' }}>
            @if($rate->buy_enabled)
                {{ auth()->check() ? 'Pay with MoMo / Card' : 'Log in to trade' }}
            @else
                {{ auth()->check() ? "I've sent the USDT" : 'Log in to trade' }}
            @endif
        </button>
        @guest <p class="text-center text-xs text-ink-faint"><a href="/login" class="underline">Log in</a> first.</p> @endguest
    </form>
    @endif

    {{-- Recent orders --}}
    @if($orders->count())
    <div>
        <h2 class="font-display mb-3 font-semibold">Your recent orders</h2>
        <div class="space-y-2">
            @foreach($orders as $o)
            @php
                $badgeClass = match($o->status) {
                    'COMPLETED' => 'badge-completed',
                    'CANCELLED' => 'badge-cancelled',
                    default => 'badge-pending',
                };
            @endphp
            <div class="card flex items-center justify-between p-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-display font-semibold">{{ $o->type === 'BUY' ? 'Buy' : 'Sell' }} {{ $o->usdt_amount }} USDT</span>
                        <span class="rounded-full bg-paper-dim px-2 py-0.5 text-[11px] font-medium text-ink-faint">{{ $o->network }}</span>
                    </div>
                    <div class="mt-0.5 text-sm text-ink-faint">₵{{ number_format($o->ghs_amount, 2) }} · {{ $o->created_at->diffForHumans() }}</div>
                </div>
                <span class="badge {{ $badgeClass }}">{{ str_replace('_', ' ', $o->status) }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
function switchTab(type) {
    document.getElementById('crypto_type').value = type;
    document.getElementById('tab-BUY').className = type === 'BUY'
        ? 'flex-1 rounded-lg py-2.5 text-sm font-semibold bg-surface shadow-sm'
        : 'flex-1 rounded-lg py-2.5 text-sm font-semibold text-ink-muted';
    document.getElementById('tab-SELL').className = type === 'SELL'
        ? 'flex-1 rounded-lg py-2.5 text-sm font-semibold bg-surface shadow-sm'
        : 'flex-1 rounded-lg py-2.5 text-sm font-semibold text-ink-muted';
    var buyFields = document.getElementById('buy-fields');
    var sellFields = document.getElementById('sell-fields');
    if (buyFields) buyFields.classList.toggle('hidden', type !== 'BUY');
    if (sellFields) sellFields.classList.toggle('hidden', type !== 'SELL');
    document.getElementById('ghs_label').textContent = type === 'BUY' ? 'Amount to pay (GHS)' : "Amount you'll receive (GHS)";
    document.getElementById('crypto-submit').textContent = type === 'BUY' ? 'Pay with MoMo / Card' : "I've sent the USDT";
    document.getElementById('active_rate').value = type === 'BUY' ? '{{ $rate->buy_rate }}' : '{{ $rate->sell_rate }}';
    document.getElementById('ghs_amount').dispatchEvent(new Event('input'));
}
</script>
@endpush
@endsection
