<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking — Freedom Data</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-paper text-ink">

<header class="bg-surface border-b border-border">
    <div class="mx-auto flex max-w-lg items-center justify-between px-4 py-3">
        <a href="/shop/{{ $agent->agent_code }}" class="flex items-center gap-2.5">
            <span class="signal-bars"><span class="bar lit"></span><span class="bar lit"></span><span class="bar lit"></span><span class="bar"></span></span>
            <span class="font-display font-semibold tracking-tight">Freedom <span style="color:var(--color-gold)">Data</span></span>
        </a>
        <nav class="flex items-center gap-2 text-sm">
            <a href="/shop/{{ $agent->agent_code }}" class="nav-pill">Buy Data</a>
            <a href="/shop/{{ $agent->agent_code }}/track" class="nav-pill active">Track Order</a>
        </nav>
    </div>
</header>

<main class="mx-auto max-w-lg px-4 py-8">
    @if(session('success'))
        <div class="mb-6 rounded-xl bg-signal-tint px-4 py-4 text-sm text-signal flex items-center gap-2">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            {{ session('success') }}
        </div>
    @endif

    @php
        $isTerminal = in_array($order->status, ['DELIVERED', 'FAILED', 'REFUNDED']);
        $isFailure = in_array($order->status, ['FAILED', 'REFUNDED']);
        $isOnHold = $order->status === 'ON_HOLD';
        $steps = ['PENDING' => 1, 'PROCESSING' => 3, 'DELIVERED' => 4];
        $lit = $steps[$order->status] ?? 0;
        $statusInfo = [
            'PENDING'    => ['Order received', 'Your order has been placed and is being processed.', 'var(--color-gold)'],
            'PROCESSING' => ['Dispatching', 'Sending the bundle to the network now.', 'var(--color-gold)'],
            'ON_HOLD'    => [$order->datasika_raw_status ?? 'On Hold', 'Your order is being reviewed before delivery.', '#f59e0b'],
            'DELIVERED'  => ['Delivered!', 'The data bundle has been sent to the recipient.', 'var(--color-signal)'],
            'FAILED'     => ['Failed', 'The delivery could not be completed. Contact the vendor.', 'var(--color-alert)'],
            'REFUNDED'   => ['Refunded', 'This order has been refunded.', 'var(--color-alert)'],
        ];
        $info = $statusInfo[$order->status] ?? ['Processing', '', 'var(--color-ink-faint)'];
    @endphp

    <div class="card p-6 space-y-6" id="order-tracker" data-order-id="{{ $order->id }}" data-shop-code="{{ $agent->agent_code }}">
        {{-- Status indicator --}}
        <div class="text-center">
            <div class="flex justify-center mb-3">
                <span class="signal-bars" style="height:32px">
                    @for($i = 0; $i < 4; $i++)
                        @if($isFailure)
                            <span class="bar" style="width:7px; {{ $i === 0 ? 'background:var(--color-alert)' : 'background:var(--color-alert-tint)' }}"></span>
                        @else
                            <span class="bar {{ $i < $lit ? 'lit' : '' }}" style="width:7px"></span>
                        @endif
                    @endfor
                </span>
            </div>
            <h1 class="font-display text-xl font-bold" id="status-title" style="color: {{ $info[2] }}">{{ $info[0] }}</h1>
            <p class="mt-1 text-sm text-ink-muted" id="status-detail">{{ $info[1] }}</p>

            @unless($isTerminal)
                <div class="mt-3 flex items-center justify-center gap-1.5 text-xs text-ink-faint">
                    <span class="h-1.5 w-1.5 rounded-full bg-signal animate-pulse"></span>
                    Checking for updates...
                </div>
            @endunless
        </div>

        {{-- DataSika message box --}}
        @if($isOnHold)
            <div class="rounded-xl px-4 py-3 text-sm" style="background:#fffbeb; border:1px solid #fde68a">
                <div class="font-semibold mb-2 text-amber-700">On Hold — verifying this number with MTN</div>
                <p class="text-ink-muted leading-relaxed">
                    Your order <strong>has not failed</strong>. MTN has not accepted this number yet — it temporarily returned <strong>"Beneficiary not allowed"</strong>, so the number is being verified before your bundle is sent. This usually takes <strong>2 to 4 days</strong>, and in rare cases up to a week. You do not need to do anything while it is under review. Once it is verified, your bundle is delivered <strong>automatically</strong>, and future orders to this same number should go through normally.
                </p>
            </div>
        @elseif($order->datasika_message)
            <div class="rounded-xl px-4 py-3 text-sm"
                 style="{{ $isFailure ? 'background:#fef2f2; border:1px solid #fecaca' : 'background:#f0fdf4; border:1px solid #bbf7d0' }}">
                <div class="font-semibold mb-1 {{ $isFailure ? 'text-red-700' : 'text-green-700' }}">
                    {{ $isFailure ? 'Why did this fail?' : 'Status update' }}
                </div>
                <p class="text-ink-muted leading-relaxed">{{ $order->datasika_message }}</p>
            </div>
        @endif

        {{-- Order details --}}
        <div class="space-y-2 border-t border-border pt-4">
            <div class="flex justify-between text-sm">
                <span class="text-ink-faint">Bundle</span>
                <span class="font-display font-semibold">{{ $order->product->network }} {{ intval($order->product->bundle_gb) }}GB</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-ink-faint">Recipient</span>
                <span class="font-mono">{{ $order->recipient }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-ink-faint">Amount paid</span>
                <span class="font-mono font-semibold">₵{{ number_format($order->amount_charged, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-ink-faint">Order ID</span>
                <span class="font-mono text-xs text-ink-faint">{{ $order->id }}</span>
            </div>
        </div>

        @if($order->status === 'DELIVERED')
            <div class="rounded-xl bg-signal-tint px-4 py-3 text-center text-sm text-signal">
                <strong>Done!</strong> The data bundle is now active on {{ $order->recipient }}.
            </div>
        @endif
    </div>

    <div class="mt-6 text-center">
        <a href="/shop/{{ $agent->agent_code }}" class="text-sm text-gold-dark underline">Buy another bundle</a>
    </div>
</main>

<footer class="mt-12 border-t border-border">
    <div class="mx-auto max-w-lg px-4 py-6 text-xs text-ink-faint text-center">Powered by Freedom Data</div>
</footer>

@unless($isTerminal)
<script>
setInterval(async () => {
    try {
        const el = document.getElementById('order-tracker');
        const code = el.dataset.shopCode;
        const id = el.dataset.orderId;
        const r = await fetch(`/shop/${code}/track/${id}/status`);
        const data = await r.json();
        if (['DELIVERED', 'FAILED', 'REFUNDED'].includes(data.status)) {
            location.reload();
        }
    } catch (e) {}
}, 30000);
</script>
@endunless
</body>
</html>