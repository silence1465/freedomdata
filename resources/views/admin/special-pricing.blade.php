@extends('layouts.app')
@section('title', 'Special Agent Pricing — Freedom Data')
@section('content')
<div class="space-y-6">
    <div>
        <h1 class="font-display text-2xl font-semibold">Special Agent Pricing</h1>
        <p class="text-sm text-ink-muted">Set custom prices for specific agents. These override the default agent price.</p>
    </div>
    <div class="flex gap-2 text-sm overflow-x-auto">
        <a href="/admin" class="nav-pill whitespace-nowrap">Dashboard</a>
        <a href="/admin/pricing" class="nav-pill whitespace-nowrap">Pricing</a>
        <a href="/admin/crypto" class="nav-pill whitespace-nowrap">USDT Desk</a>
        <a href="/admin/agents" class="nav-pill whitespace-nowrap">Agents</a>
        <a href="/admin/payouts" class="nav-pill whitespace-nowrap">Payouts</a>
        <a href="/admin/special-pricing" class="nav-pill active whitespace-nowrap">Special Pricing</a>
        <a href="/admin/sms-devices" class="nav-pill {{ request()->is('admin/sms-devices') ? 'active' : '' }} whitespace-nowrap">sms-devices</a>
    </div>

    {{-- Select agent --}}
    <form method="GET" action="/admin/special-pricing" class="card p-5">
        <label class="mb-2 block text-sm font-medium">Select an agent to set their special prices</label>
        <div class="flex gap-3 items-end">
            <select name="agent_id" class="form-input flex-1" onchange="this.form.submit()">
                <option value="">Choose an agent...</option>
                @foreach($agents as $a)
                    <option value="{{ $a->id }}" {{ $selectedAgentId == $a->id ? 'selected' : '' }}>
                        {{ $a->name ?? 'No name' }} — {{ $a->phone }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    {{-- Price editor --}}
    @if($selectedAgentId)
        @php $agent = $agents->firstWhere('id', $selectedAgentId); @endphp
        <form method="POST" action="/admin/special-pricing" class="card">
            @csrf
            <input type="hidden" name="agent_id" value="{{ $selectedAgentId }}">
            <div class="border-b border-border p-4 flex items-center justify-between">
                <div>
                    <div class="font-display font-semibold">Prices for {{ $agent->name ?? $agent->phone }}</div>
                    <div class="text-sm text-ink-muted">Leave blank to use the default agent price. Fill in to override.</div>
                </div>
                <button type="submit" class="btn-gold">Save prices</button>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-ink-faint">
                        <th class="p-3 font-medium">Bundle</th>
                        <th class="p-3 font-medium">Regular sell</th>
                        <th class="p-3 font-medium">Default agent</th>
                        <th class="p-3 font-medium">Special price for this agent</th>
                        <th class="p-3 font-medium">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products->groupBy('network') as $network => $items)
                        <tr class="bg-paper-dim">
                            <td colspan="5" class="p-2 px-3 font-display text-xs font-semibold uppercase tracking-wide text-ink-faint">{{ $network }}</td>
                        </tr>
                        @foreach($items as $p)
                            @php
                                $sp = $specialPrices[$p->id] ?? '';
                                $agentDefault = $p->agent_price ?? $p->sell_price;
                                $effectivePrice = $sp !== '' ? (float)$sp : (float)$agentDefault;
                                $margin = round($effectivePrice - (float)$p->cost_price, 2);
                            @endphp
                            <tr class="border-b border-border last:border-0 {{ $sp !== '' ? 'bg-gold-tint/20' : '' }}">
                                <td class="p-3 font-medium">{{ intval($p->bundle_gb) }}GB</td>
                                <td class="p-3 font-mono text-ink-faint">₵{{ number_format($p->sell_price, 2) }}</td>
                                <td class="p-3 font-mono text-ink-faint">₵{{ number_format($agentDefault, 2) }}</td>
                                <td class="p-3">
                                    <input name="prices[{{ $p->id }}]"
                                           value="{{ $sp }}"
                                           placeholder="{{ number_format($agentDefault, 2) }}"
                                           class="form-input w-28 font-mono text-sm py-1 px-2 {{ $sp !== '' ? 'border-gold' : '' }}"
                                           onchange="updateMargin(this, {{ $p->cost_price }}, {{ $agentDefault }})">
                                </td>
                                <td class="p-3 font-mono {{ $margin > 0 ? 'text-signal' : 'text-alert' }}">
                                    <span class="margin-display">₵{{ number_format($margin, 2) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </form>
    @endif
</div>

@push('scripts')
<script>
function updateMargin(input, cost, defaultAgent) {
    var row = input.closest('tr');
    var display = row.querySelector('.margin-display');
    var val = parseFloat(input.value);
    var price = isNaN(val) ? defaultAgent : val;
    var margin = (price - cost).toFixed(2);
    display.textContent = (margin >= 0 ? '₵' : '-₵') + Math.abs(margin).toFixed(2);
    display.closest('td').className = margin > 0 ? 'p-3 font-mono text-signal' : 'p-3 font-mono text-alert';
}
</script>
@endpush
@endsection
