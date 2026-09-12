@extends('layouts.app')
@section('title', 'Agent Dashboard — Freedom Data')
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="font-display text-2xl font-semibold">Agent Dashboard</h1>
            <p class="text-sm text-ink-muted">
                Subscription active until <strong>{{ $user->agent_expires_at->format('M d, Y') }}</strong>
                ({{ $user->agent_expires_at->diffForHumans() }})
            </p>
        </div>
        <a href="/agent/subscribe" class="btn-gold text-sm">Renew subscription</a>
    </div>

    {{-- Shareable shop link --}}
    @if($user->agent_code)
    <div class="card p-5">
        <h2 class="font-display font-semibold mb-2">Your shop link</h2>
        <p class="text-sm text-ink-muted mb-3">Share this link with your customers. They can buy bundles at your prices and pay via MoMo/card. You earn the margin automatically.</p>
        <div class="flex items-center gap-2">
            <input type="text" readonly value="{{ url('/shop/' . $user->agent_code) }}" id="shop-link" class="form-input font-mono text-sm flex-1" onclick="this.select()">
            <button onclick="navigator.clipboard.writeText(document.getElementById('shop-link').value); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy', 2000)" class="btn-dark whitespace-nowrap">Copy</button>
        </div>
    </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="stat-card">
            <div class="text-xs text-ink-faint">Wallet balance</div>
            <div class="font-display mt-1 text-2xl font-semibold text-signal">₵{{ number_format($user->wallet_balance, 2) }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs text-ink-faint">Total orders</div>
            <div class="font-display mt-1 text-2xl font-semibold">{{ $orders->count() }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs text-ink-faint">Subscription expires</div>
            <div class="font-display mt-1 text-lg font-semibold">{{ $user->agent_expires_at->format('M d, Y') }}</div>
        </div>
    </div>

    {{-- Resell pricing editor --}}
    <form method="POST" action="/agent/prices" class="card">
        @csrf
        <div class="border-b border-border p-4 flex items-center justify-between">
            <div>
                <div class="font-display font-semibold">Your resell prices</div>
                <div class="text-sm text-ink-muted">Set what you charge your customers. Your cost is the agent price — the difference is your margin.</div>
            </div>
            <button type="submit" class="btn-dark">Save all prices</button>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border text-left text-ink-faint">
                    <th class="p-3 font-medium">Bundle</th>
                    <th class="p-3 font-medium">Your cost (agent price)</th>
                    <th class="p-3 font-medium">Your resell price</th>
                    <th class="p-3 font-medium">Your margin</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products->groupBy('network') as $network => $items)
                    <tr class="bg-paper-dim">
                        <td colspan="4" class="p-2 px-3 font-display text-xs font-semibold uppercase tracking-wide text-ink-faint">{{ $network }}</td>
                    </tr>
                    @foreach($items as $p)
                        @php
                            $agentCost = $p->agent_price ?? $p->sell_price;
                            $resell = $resellPrices[$p->id] ?? '';
                            $margin = $resell ? round($resell - $agentCost, 2) : null;
                        @endphp
                        <tr class="border-b border-border last:border-0">
                            <td class="p-3 font-medium">{{ intval($p->bundle_gb) }}GB</td>
                            <td class="p-3 font-mono text-ink-faint">₵{{ number_format($agentCost, 2) }}</td>
                            <td class="p-3">
                                <input name="prices[{{ $p->id }}]"
                                       value="{{ $resell }}"
                                       placeholder="e.g. {{ number_format($agentCost * 1.15, 2) }}"
                                       class="form-input w-28 font-mono text-sm py-1 px-2"
                                       onchange="updateMargin(this, {{ $agentCost }})">
                            </td>
                            <td class="p-3 font-mono {{ $margin !== null && $margin > 0 ? 'text-signal' : 'text-ink-faint' }}">
                                <span class="margin-display">{{ $margin !== null ? '₵' . number_format($margin, 2) : '—' }}</span>
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </form>

    {{-- Recent orders --}}
    @if($orders->count())
    <div class="card overflow-x-auto">
        <div class="border-b border-border p-4 font-display font-semibold">Your recent orders</div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border text-left text-ink-faint">
                    <th class="p-3 font-medium">Bundle</th>
                    <th class="p-3 font-medium">Recipient</th>
                    <th class="p-3 font-medium">Cost</th>
                    <th class="p-3 font-medium">Status</th>
                    <th class="p-3 font-medium">Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $o)
                <tr class="border-b border-border last:border-0">
                    <td class="p-3">{{ $o->product->network }} {{ intval($o->product->bundle_gb) }}GB</td>
                    <td class="p-3 font-mono">{{ $o->recipient }}</td>
                    <td class="p-3 font-mono">₵{{ number_format($o->amount_charged, 2) }}</td>
                    <td class="p-3"><span class="badge badge-{{ strtolower($o->status) }}">{{ $o->status }}</span></td>
                    <td class="p-3 text-ink-faint">{{ $o->created_at->diffForHumans() }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@push('scripts')
<script>
function updateMargin(input, cost) {
    const row = input.closest('tr');
    const display = row.querySelector('.margin-display');
    const val = parseFloat(input.value);
    if (isNaN(val)) { display.textContent = '—'; return; }
    const margin = (val - cost).toFixed(2);
    display.textContent = (margin >= 0 ? '₵' : '-₵') + Math.abs(margin).toFixed(2);
    display.closest('td').className = margin > 0 ? 'p-3 font-mono text-signal' : 'p-3 font-mono text-alert';
}
</script>
@endpush
@endsection
