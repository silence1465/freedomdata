<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $agent->name ?? 'Data Shop' }} — Freedom Data</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        .shop-hero { background: linear-gradient(135deg, #14181f 0%, #1e293b 100%); }
        .glow-card { transition: all 200ms; }
        .glow-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
        .glow-card.selected { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(200,135,30,0.2); }
        .trust-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 500; }
    </style>
</head>
<body class="min-h-screen bg-paper text-ink">

{{-- Header --}}
<header class="bg-surface border-b border-border">
    <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
        <a href="/shop/{{ $agent->agent_code }}" class="flex items-center gap-2.5">
            <span class="signal-bars"><span class="bar lit"></span><span class="bar lit"></span><span class="bar lit"></span><span class="bar"></span></span>
            <span class="font-display font-semibold tracking-tight">Freedom <span style="color:var(--color-gold)">Data</span></span>
        </a>
        <nav class="flex items-center gap-2 text-sm">
            <a href="/shop/{{ $agent->agent_code }}" class="nav-pill active">Buy Data</a>
            <a href="/shop/{{ $agent->agent_code }}/track" class="nav-pill">Track Order</a>
        </nav>
    </div>
</header>

{{-- Hero --}}
<div class="shop-hero text-white">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:py-10">
        <p class="text-white/60 text-sm mb-1">Sold by</p>
        <h1 class="font-display text-2xl sm:text-3xl font-bold">{{ $agent->name ? $agent->name . "'s Data Shop" : 'Data Shop' }}</h1>
        <p class="mt-2 text-white/70 max-w-md">Instant data bundles for any Ghana number. Select a bundle, enter the number, pay — done in under a minute.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <span class="trust-badge" style="background:rgba(255,255,255,0.1)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Instant delivery
            </span>
            <span class="trust-badge" style="background:rgba(255,255,255,0.1)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Secure payment
            </span>
            <span class="trust-badge" style="background:rgba(255,255,255,0.1)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                MoMo &amp; Card
            </span>
        </div>
    </div>
</div>

<main class="mx-auto max-w-5xl px-4 py-8">
    @if(session('success'))
        <div class="mb-6 rounded-xl bg-signal-tint px-4 py-4 text-sm text-signal flex items-center gap-2">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 rounded-xl bg-alert-tint px-4 py-3 text-sm text-alert">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-6 rounded-xl bg-alert-tint px-4 py-3 text-sm text-alert">
            @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
        </div>
    @endif

    @php
        $networkColors = ['MTN Flexa' => '#ffcc08','MTN' => '#ffcc08', 'Telecel' => '#e4032e', 'AirtelTigo' => '#1b4f9c'];
        $networkBg = ['MTN Flexa','MTN' => '#fffdf5', 'Telecel' => '#fff8f8', 'AirtelTigo' => '#f5f8ff'];
        $networkIcon = ['MTN Flexa' => 'MF','MTN' => 'M', 'Telecel' => 'T', 'AirtelTigo' => 'A'];
        $grouped = $products->groupBy('network');
    @endphp

    @if($products->count())
    <div class="grid gap-8 lg:grid-cols-3">
        {{-- Bundles --}}
        <div class="space-y-5 lg:col-span-2">
            <h2 class="font-display text-lg font-semibold">Choose a bundle</h2>

            @foreach(['MTN Flexa', 'MTN', 'Telecel', 'AirtelTigo'] as $network)
                @if($grouped->has($network))
                <div class="rounded-2xl border overflow-hidden" style="border-color: {{ $networkColors[$network] }}30">
                    <div class="flex items-center gap-3 px-4 py-3" style="background: {{ $networkBg[$network] }}">
                        <x-network-logo :network="$network" size="sm" />
                        <span class="font-display font-semibold">{{ $network }}</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 p-3" style="background: {{ $networkBg[$network] }}">
                        @foreach($grouped[$network] as $p)
                            @php $price = $resellPrices[$p->id]; @endphp
                            <div class="glow-card rounded-xl bg-white p-4 border border-border cursor-pointer shadow-sm"
                                 data-id="{{ $p->id }}"
                                 data-network="{{ $network }}"
                                 data-gb="{{ intval($p->bundle_gb) }}"
                                 data-price="{{ number_format($price, 2) }}"
                                 data-color="{{ $networkColors[$network] }}"
                                 onclick="selectBundle(this)">
                                <div class="font-display text-2xl font-bold">{{ intval($p->bundle_gb) }}<span class="text-xs font-semibold text-ink-muted">GB</span></div>
                                <div class="mt-1.5 font-mono text-base font-semibold" style="color: {{ $networkColors[$network] }}">₵{{ number_format($price, 2) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif
            @endforeach
        </div>

        {{-- Checkout --}}
        <div class="lg:col-span-1">
            <form method="POST" action="{{ $paystackEnabled ? '/shop/'.$agent->agent_code.'/buy' : '/shop/'.$agent->agent_code.'/manual-init' }}" class="card p-5 space-y-4 lg:sticky lg:top-6">
                <h2 class="font-display text-lg font-semibold">Checkout</h2>
                <input type="hidden" name="product_id" id="product_id">
                @csrf

                <div id="selected-info" class="rounded-xl border-2 border-dashed border-border-strong bg-paper-dim px-4 py-4 text-center text-sm text-ink-faint">
                    <svg class="mx-auto mb-2" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-ink-faint)"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    Select a bundle to continue
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Recipient phone number</label>
                    <input type="tel" name="recipient" placeholder="0241234567" class="form-input font-mono" inputmode="numeric" required value="{{ old('recipient') }}">
                </div>
                <div class="hidden">
                    <label class="mb-1 block text-sm font-medium">Your email <span class="text-ink-faint">(for receipt)</span></label>
                    <input type="email" name="customer_email" value="gebenezer07@gmail.com" class="form-input" required value="{{ old('customer_email') }}">
                </div>
                <div class="hidden">
                    <label class="mb-1 block text-sm font-medium">Your name <span class="text-ink-faint">(optional)</span></label>
                    <input type="text" name="customer_name" value="John Agent" class="form-input" value="{{ old('customer_name') }}">
                </div>

                <button type="submit" class="btn-gold w-full py-3.5 text-base flex items-center justify-center gap-2">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    {{ $paystackEnabled ? 'Pay with MoMo / Card' : 'Pay via MoMo' }}
                </button>
                <p class="text-center text-[11px] text-ink-faint">{{ $paystackEnabled ? 'Secured by Paystack.' : 'You will receive payment instructions after submitting.' }}</p>
            </form>
        </div>
    </div>
    @else
    <div class="card p-10 text-center">
        <svg class="mx-auto mb-3" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-ink-faint)"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <p class="font-display text-lg font-semibold">No bundles available yet</p>
        <p class="mt-1 text-sm text-ink-muted">This vendor is still setting up their pricing. Check back soon.</p>
    </div>
    @endif
</main>

<footer class="mt-12 border-t border-border">
    <div class="mx-auto max-w-5xl px-4 py-6 flex items-center justify-between">
        <span class="text-xs text-ink-faint">Powered by Freedom Data</span>
        <div class="flex items-center gap-1.5">
            <span class="signal-bars" style="height:14px">
                <span class="bar lit" style="width:3px;background:var(--color-ink-faint)"></span>
                <span class="bar lit" style="width:3px;background:var(--color-ink-faint)"></span>
                <span class="bar" style="width:3px"></span>
                <span class="bar" style="width:3px"></span>
            </span>
        </div>
    </div>
</footer>

<script>
function selectBundle(card) {
    document.querySelectorAll('.glow-card').forEach(c => {
        c.classList.remove('selected');
        c.style.borderColor = '';
    });
    card.classList.add('selected');
    card.style.borderColor = card.dataset.color;

    document.getElementById('product_id').value = card.dataset.id;
    const info = document.getElementById('selected-info');
    info.className = 'rounded-xl border-2 bg-gold-tint px-4 py-4 text-center';
    info.style.borderColor = card.dataset.color;
    info.innerHTML = '<div class="font-display font-bold text-xl">' + card.dataset.network + ' ' + card.dataset.gb + 'GB</div><div class="font-mono text-lg font-semibold" style="color:' + card.dataset.color + '">₵' + card.dataset.price + '</div>';

    // Mobile: scroll to checkout
    if (window.innerWidth < 1024) {
        var form = info.closest('form') || info.closest('.card');
        if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Loading overlay on checkout submit
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form[action*="/shop/"]');
    if (form) {
        form.addEventListener('submit', (e) => {
            if (!document.getElementById('product_id').value) {
                e.preventDefault();
                alert('Please select a bundle first.');
                return;
            }
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;';
            overlay.innerHTML = '<div style="width:52px;height:52px;border:4px solid rgba(255,255,255,0.2);border-top-color:#fff;border-radius:50%;animation:spin 0.8s linear infinite;"></div><div style="color:#fff;font-size:16px;font-weight:600;">Processing payment...</div><div style="color:rgba(255,255,255,0.6);font-size:13px;">Please wait, do not close this page.</div><style>@keyframes spin{to{transform:rotate(360deg)}}</style>';
            document.body.appendChild(overlay);
        });
    }
});
</script>
</body>
</html>
