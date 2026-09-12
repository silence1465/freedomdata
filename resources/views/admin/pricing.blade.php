@extends('layouts.app')
@section('title', 'Pricing — Freedom Data')
@section('content')
<div class="space-y-6">
    <div>
        <h1 class="font-display text-2xl font-semibold">Bundle pricing</h1>
        <p class="text-sm text-ink-muted">Set your sell and agent prices. Cost is what DataSika charges you.</p>
    </div>
    <div class="flex gap-2 text-sm">
        <a href="/admin" class="nav-pill">Dashboard</a>
        <a href="/admin/pricing" class="nav-pill active">Pricing</a>
        <a href="/admin/crypto" class="nav-pill">USDT Desk</a>
        <a href="/admin/agents" class="nav-pill">Agents</a>
        <a href="/admin/payouts" class="nav-pill {{ request()->is('admin/payouts') ? 'active' : '' }} whitespace-nowrap">Payouts</a>
        <a href="/admin/special-pricing" class="nav-pill {{ request()->is('admin/special-pricing') ? 'active' : '' }} whitespace-nowrap">special Pricing</a>
        <a href="/admin/sms-devices" class="nav-pill {{ request()->is('admin/sms-devices') ? 'active' : '' }} whitespace-nowrap">sms-devices</a>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border text-left text-ink-faint">
                    <th class="p-3 font-medium">Bundle</th>
                    <th class="p-3 font-medium">Cost</th>
                    <th class="p-3 font-medium">Sell price</th>
                    <th class="p-3 font-medium">Agent price</th>
                    <th class="p-3 font-medium">Margin</th>
                    <th class="p-3 font-medium">Status</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $p)
                <tr class="border-b border-border last:border-0">
                    <form method="POST" action="/admin/pricing/{{ $p->id }}">
                        @csrf
                        <td class="p-3 font-display font-medium">{{ $p->network }} {{ intval($p->bundle_gb) }}GB</td>
                        <td class="p-3 font-mono text-ink-faint">₵{{ number_format($p->cost_price, 2) }}</td>
                        <td class="p-3"><input name="sell_price" value="{{ $p->sell_price }}" class="form-input w-24 font-mono text-sm py-1 px-2" required></td>
                        <td class="p-3"><input name="agent_price" value="{{ $p->agent_price }}" placeholder="—" class="form-input w-24 font-mono text-sm py-1 px-2"></td>
                        <td class="p-3 font-mono text-signal">+{{ $p->cost_price > 0 ? round(($p->sell_price - $p->cost_price) / $p->cost_price * 100) : 0 }}%</td>
                        <td class="p-3">
                            <span class="badge {{ $p->is_available ? 'badge-delivered' : 'badge-failed' }}">{{ $p->is_available ? 'Live' : 'Off' }}</span>
                        </td>
                        <td class="p-3"><button type="submit" class="btn-dark">Save</button></td>
                    </form>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
