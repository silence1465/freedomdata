<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order — Freedom Data</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-paper text-ink">

<header class="bg-surface border-b border-border">
    <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
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

<main class="mx-auto max-w-3xl px-4 py-8">
    @if(session('success'))
        <div class="mb-6 rounded-xl bg-signal-tint px-4 py-4 text-sm text-signal flex items-center gap-2">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Search --}}
    <div class="mb-8">
        <h1 class="font-display text-2xl font-bold mb-1">Track your orders</h1>
        <p class="text-sm text-ink-muted mb-4">Enter the phone number used for the purchase to view all your transactions.</p>

        <form method="GET" action="/shop/{{ $agent->agent_code }}/track" class="flex gap-2">
            <input type="tel" name="phone" value="{{ $phone ?? '' }}" placeholder="0241234567" class="form-input font-mono flex-1" inputmode="numeric" required>
            <button type="submit" class="btn-gold whitespace-nowrap px-6">Search</button>
        </form>
    </div>

    {{-- Results --}}
    @if($phone && $orders !== null)
        @if($orders->total() > 0)
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-ink-muted">
                    {{ $orders->total() }} {{ $orders->total() === 1 ? 'order' : 'orders' }} found for
                    <span class="font-mono font-semibold text-ink">{{ $phone }}</span>
                </p>
            </div>

            <div class="space-y-3">
                @foreach($orders as $o)
                    @php
                        $statusColors = [
                            'DELIVERED' => ['bg-signal-tint', 'text-signal'],
                            'PENDING' => ['bg-gold-tint', 'text-gold-dark'],
                            'PROCESSING' => ['bg-gold-tint', 'text-gold-dark'],
                            'FAILED' => ['bg-alert-tint', 'text-alert'],
                            'REFUNDED' => ['bg-paper-dim', 'text-ink-muted'],
                        ];
                        $sc = $statusColors[$o->status] ?? ['bg-paper-dim', 'text-ink-muted'];
                        $networkColors = ['MTN' => '#ffcc08', 'Telecel' => '#e4032e', 'AirtelTigo' => '#1b4f9c'];
                    @endphp
                    <div class="card p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <x-network-logo :network="$o->product->network" size="xs" />
                                <span class="font-display font-semibold">{{ $o->product->network }} {{ intval($o->product->bundle_gb) }}GB</span>
                            </div>
                            <span class="badge {{ $sc[0] }} {{ $sc[1] }}">{{ $o->status }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <div class="text-ink-faint">
                                <span class="font-mono">₵{{ number_format($o->amount_charged, 2) }}</span>
                                <span class="mx-1">·</span>
                                {{ $o->created_at->format('M d, Y · h:i A') }}
                            </div>
                            @if($o->status === 'DELIVERED')
                                <span class="text-signal text-xs font-medium flex items-center gap-1">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    Delivered
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($orders->hasPages())
                <div class="mt-6 flex items-center justify-center gap-1">
                    @if($orders->onFirstPage())
                        <span class="px-3 py-2 text-sm text-ink-faint">← Previous</span>
                    @else
                        <a href="{{ $orders->previousPageUrl() }}" class="nav-pill text-sm">← Previous</a>
                    @endif

                    <span class="px-3 py-2 text-sm text-ink-muted">
                        Page {{ $orders->currentPage() }} of {{ $orders->lastPage() }}
                    </span>

                    @if($orders->hasMorePages())
                        <a href="{{ $orders->nextPageUrl() }}" class="nav-pill text-sm">Next →</a>
                    @else
                        <span class="px-3 py-2 text-sm text-ink-faint">Next →</span>
                    @endif
                </div>
            @endif

        @else
            <div class="card p-8 text-center">
                <svg class="mx-auto mb-3" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-ink-faint)"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <p class="font-display font-semibold">No orders found</p>
                <p class="mt-1 text-sm text-ink-muted">We couldn't find any orders for <span class="font-mono">{{ $phone }}</span>. Make sure you entered the correct phone number.</p>
                <a href="/shop/{{ $agent->agent_code }}" class="inline-block mt-4 text-sm text-gold-dark underline">Buy a data bundle</a>
            </div>
        @endif
    @endif
</main>

<footer class="mt-12 border-t border-border">
    <div class="mx-auto max-w-3xl px-4 py-6 text-xs text-ink-faint text-center">Powered by Freedom Data</div>
</footer>
</body>
</html>
