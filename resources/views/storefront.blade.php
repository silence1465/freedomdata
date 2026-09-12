@extends('layouts.app')
@section('title', 'Buy Data Bundles — Freedom Data')
@section('content')

{{-- Hero --}}
<section class="mb-10 flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
    <div class="max-w-xl">
        <span class="signal-bars"><span class="bar lit"></span><span class="bar lit"></span><span class="bar lit"></span><span class="bar lit"></span></span>
        <h1 class="font-display mt-4 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight">Data, sent in minutes.</h1>
        <p class="mt-3 text-ink-muted">Buy MTN, Telecel and AirtelTigo bundles from your wallet. Track every order live.</p>
    </div>
    <div class="card px-5 py-4 text-sm font-mono text-ink-faint hidden sm:block">Prices in GHS · updated automatically</div>
</section>

@if(!$bundleEnabled)
    <div class="card p-8 text-center mb-8">
        <svg class="mx-auto mb-3" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-gold)"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <p class="font-display text-lg font-semibold">Data bundle service is currently paused</p>
        <p class="mt-1 text-sm text-ink-muted">We are temporarily unable to process bundle purchases. Please check back shortly.</p>
    </div>
@else

@php
    $networkColors = ['MTN Flexa' => '#ffcc08', 'MTN' => '#ffcc08', 'Telecel' => '#e4032e', 'AirtelTigo' => '#1b4f9c'];
    $networkBg = ['MTN Flexa' => '#ffcc08', 'MTN' => '#fffdf5', 'Telecel' => '#fff8f8', 'AirtelTigo' => '#f5f8ff'];
    $isAgent = auth()->check() && auth()->user()->isActiveAgent();
    $specialPrices = $isAgent ? \App\Models\AgentSpecialPrice::where('user_id', auth()->id())->pluck('special_price', 'product_id') : collect();
    $networkOrder = ['MTN Flexa', 'MTN', 'Telecel', 'AirtelTigo'];
    $sortedGroups = collect($networkOrder)->filter(fn($n) => $grouped->has($n))->mapWithKeys(fn($n) => [$n => $grouped[$n]]);
@endphp

<div class="grid gap-8 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        @foreach($sortedGroups as $network => $products)
            <div class="rounded-2xl border overflow-hidden" style="border-color: {{ $networkColors[$network] ?? '#e7e2d9' }}40">
                {{-- Network header --}}
                <div class="flex items-center gap-3 px-4 sm:px-5 py-3 sm:py-4" style="background: {{ $networkBg[$network] ?? '#f7f5f1' }}">
                    <x-network-logo :network="$network" size="md" />
                    <div>
                        <div class="font-display text-base sm:text-lg font-semibold">{{ $network }}</div>
                        <div class="text-xs text-ink-muted">{{ $products->count() }} bundles available</div>
                    </div>
                    @if($isAgent)
                        <span class="ml-auto rounded-full bg-signal-tint px-2.5 py-1 text-[11px] font-semibold text-signal">Agent pricing</span>
                    @endif
                </div>

                {{-- Bundle grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5 sm:gap-3 p-3 sm:p-4" style="background: {{ $networkBg[$network] ?? '#f7f5f1' }}">
                    @foreach($products as $p)
                        @php
                            $displayPrice = $p->sell_price;
                            $priceLabel = '';
                            if ($isAgent) {
                                if ($specialPrices->has($p->id)) {
                                    $displayPrice = $specialPrices[$p->id];
                                    $priceLabel = 'Special price';
                                } elseif ($p->agent_price) {
                                    $displayPrice = $p->agent_price;
                                    $priceLabel = 'Agent price';
                                }
                            }
                        @endphp
                        <div class="bundle-card rounded-xl bg-white p-3 sm:p-4 border border-border shadow-md hover:shadow-lg transition-shadow"
                             data-id="{{ $p->id }}"
                             data-network="{{ $p->network }}"
                             data-gb="{{ intval($p->bundle_gb) }}"
                             data-price="{{ number_format($displayPrice, 2) }}">
                            <div class="font-display text-xl sm:text-2xl font-bold">{{ intval($p->bundle_gb) }}<span class="text-xs sm:text-sm font-semibold text-ink-muted">GB</span></div>
                            <div class="mt-1 font-mono text-sm sm:text-base font-semibold" style="color: {{ $networkColors[$network] ?? 'var(--color-gold-dark)' }}">₵{{ number_format($displayPrice, 2) }}</div>
                            @if($isAgent && $p->agent_price)
                                <div class="mt-0.5 text-[11px] text-ink-faint line-through">₵{{ number_format($p->sell_price, 2) }}</div>
                            @endif
                            @if($priceLabel)
                                <span class="mt-1 inline-block rounded-full {{ $priceLabel === 'Special price' ? 'bg-gold-tint text-gold-dark' : 'bg-signal-tint text-signal' }} px-2 py-0.5 text-[10px] font-semibold">{{ $priceLabel }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- Checkout --}}
    <div class="card h-fit space-y-4 p-5 lg:sticky lg:top-6" id="checkout-panel">
        <h2 class="font-display text-lg font-semibold">Complete purchase</h2>
        <input type="hidden" id="product_id" value="">

        <div id="selected-info" class="rounded-xl border-2 border-dashed border-border-strong bg-paper-dim px-4 py-4 text-center text-sm text-ink-faint">
            Tap a bundle to select it.
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Recipient number</label>
            <input type="tel" id="recipient" placeholder="0241234567" value="{{ old('recipient') }}" class="form-input font-mono" inputmode="numeric">
        </div>

        @auth
            @php $isAgent = auth()->user()->isActiveAgent(); @endphp

            {{-- Wallet payment --}}
            <form method="POST" action="/buy" id="wallet-form">
                @csrf
                <input type="hidden" name="product_id" id="wallet_product_id">
                <input type="hidden" name="recipient" id="wallet_recipient">
                <button type="submit" class="btn-gold w-full py-3 text-base">
                    Pay from wallet (₵{{ number_format(auth()->user()->wallet_balance, 2) }})
                </button>
            </form>

            @if(!$isAgent)
                @if($paystackEnabled)
                    <div class="text-center text-xs text-ink-faint">or</div>
                    <form method="POST" action="/buy/paystack" id="paystack-form">
                        @csrf
                        <input type="hidden" name="product_id" id="paystack_product_id">
                        <input type="hidden" name="recipient" id="paystack_recipient">
                        <button type="submit" class="w-full py-3 text-base font-display font-semibold rounded-lg border-2 border-ink text-ink hover:bg-ink hover:text-white transition">
                            Pay with MoMo / Card
                        </button>
                    </form>
                @else
                    <form method="POST" action="/buy/manual-init" id="manual-form">
                        @csrf
                        <input type="hidden" name="product_id" id="manual_product_id">
                        <input type="hidden" name="recipient" id="manual_recipient">
                        <button type="submit" class="btn-gold w-full py-3 text-base">Pay via MoMo</button>
                    </form>
                @endif
            @else
                <p class="text-center text-xs text-ink-faint">
                    Agent accounts use wallet only. <a href="/wallet" class="underline">Top up your wallet</a>
                </p>
            @endif
        @else
            @if($paystackEnabled)
                <div>
                    <label class="mb-1 block text-sm font-medium">Your email <span class="text-ink-faint">(for receipt)</span></label>
                    <input type="email" id="guest_email" placeholder="you@example.com" class="form-input">
                </div>
                <form method="POST" action="/buy/guest" id="guest-form">
                    @csrf
                    <input type="hidden" name="product_id" id="guest_product_id">
                    <input type="hidden" name="recipient" id="guest_recipient">
                    <input type="hidden" name="email" id="guest_email_hidden">
                    <button type="submit" class="btn-gold w-full py-3 text-base">
                        Pay with MoMo / Card
                    </button>
                </form>
                <p class="text-center text-xs text-ink-faint">
                    No account needed. <a href="/login" class="underline">Log in</a> for wallet purchases.
                </p>
            @else
                <form method="POST" action="/buy/guest-manual-init" id="manual-form">
                    @csrf
                    <input type="hidden" name="product_id" id="manual_product_id">
                    <input type="hidden" name="recipient" id="manual_recipient">
                    <button type="submit" class="btn-gold w-full py-3 text-base">Pay via MoMo</button>
                </form>
                <p class="text-center text-xs text-ink-faint mt-1">
                    <a href="/login" class="underline">Log in</a> to pay from wallet.
                </p>
            @endif
        @endauth
    </div>
</div>
@endif
@endsection
