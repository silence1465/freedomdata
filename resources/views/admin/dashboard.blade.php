@extends('layouts.app')
@section('title', 'Admin — Freedom Data')
@section('content')
<div class="space-y-6">
    <div>
        <h1 class="font-display text-2xl font-semibold">Control tower</h1>
        <p class="text-sm text-ink-muted">Orders, revenue, crypto — everything at a glance.</p>
    </div>

    <div class="flex gap-2 text-sm overflow-x-auto">
        <a href="/admin" class="nav-pill {{ request()->is('admin') ? 'active' : '' }} whitespace-nowrap">Dashboard</a>
        <a href="/admin/pricing" class="nav-pill {{ request()->is('admin/pricing') ? 'active' : '' }} whitespace-nowrap">Pricing</a>
        <a href="/admin/crypto" class="nav-pill {{ request()->is('admin/crypto') ? 'active' : '' }} whitespace-nowrap">USDT Desk</a>
        <a href="/admin/agents" class="nav-pill {{ request()->is('admin/agents') ? 'active' : '' }} whitespace-nowrap">Agents</a>
        <a href="/admin/payouts" class="nav-pill {{ request()->is('admin/payouts') ? 'active' : '' }} whitespace-nowrap">Payouts</a>
        <a href="/admin/special-pricing" class="nav-pill {{ request()->is('admin/special-pricing') ? 'active' : '' }} whitespace-nowrap">special Pricing</a>
        <a href="/admin/sms-devices" class="nav-pill {{ request()->is('admin/sms-devices') ? 'active' : '' }} whitespace-nowrap">sms-devices</a>
       
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 sm:gap-4">
        <div class="stat-card">
            <div class="text-xs text-ink-faint">Total orders</div>
            <div class="font-display mt-1 text-2xl font-semibold">{{ $totalOrders }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs text-ink-faint">Revenue</div>
            <div class="font-display mt-1 text-2xl font-semibold">₵{{ number_format($revenue, 2) }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs text-ink-faint">Margin earned</div>
            <div class="font-display mt-1 text-2xl font-semibold text-signal">₵{{ number_format($revenue - $cost, 2) }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs text-ink-faint">Crypto pending</div>
            <div class="font-display mt-1 text-2xl font-semibold {{ $cryptoPending > 0 ? 'text-alert' : '' }}">{{ $cryptoPending }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs text-ink-faint">Active agents</div>
            <div class="font-display mt-1 text-2xl font-semibold">{{ $totalAgents }}</div>
        </div>
    </div>

    {{-- Manual payment orders awaiting approval --}}
    @if($manualPending > 0)
    @php $pendingManualOrders = \App\Models\Order::with('product')->where('status', 'AWAITING_APPROVAL')->orderByDesc('created_at')->get(); @endphp
    <div class="card overflow-x-auto">
        <div class="border-b border-border p-4 flex items-center justify-between">
            <div class="font-display font-semibold flex items-center gap-2">
                Manual Payments Awaiting Approval
                <span class="rounded-full bg-alert text-white text-xs font-bold px-2 py-0.5">{{ $manualPending }}</span>
            </div>
        </div>
        <table class="w-full text-sm min-w-[600px]">
            <thead>
                <tr class="border-b border-border text-left text-ink-faint">
                    <th class="p-3 font-medium">Bundle</th>
                    <th class="p-3 font-medium">Recipient</th>
                    <th class="p-3 font-medium">Amount</th>
                    <th class="p-3 font-medium">Reference</th>
                    <th class="p-3 font-medium">Submitted</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($pendingManualOrders as $o)
                <tr class="border-b border-border last:border-0 bg-gold-tint/20">
                    <td class="p-3 font-medium">{{ $o->product->network }} {{ intval($o->product->bundle_gb) }}GB</td>
                    <td class="p-3 font-mono">{{ $o->recipient }}</td>
                    <td class="p-3 font-mono">₵{{ number_format($o->amount_charged, 2) }}</td>
                    <td class="p-3 font-mono font-semibold">{{ $o->payment_reference }}</td>
                    <td class="p-3 text-ink-faint text-xs">{{ $o->created_at->diffForHumans() }}</td>
                    <td class="p-3">
                        <div class="flex gap-2">
                            <form method="POST" action="/admin/manual-orders/{{ $o->id }}/approve">
                                @csrf
                                <button class="btn-signal text-xs" onclick="return confirm('Approve and send to DataSika?')">Approve</button>
                            </form>
                            <form method="POST" action="/admin/manual-orders/{{ $o->id }}/reject">
                                @csrf
                                <button class="btn-alert text-xs" onclick="return confirm('Reject this order?')">Reject</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Service Controls --}}
    <form method="POST" action="/admin/services" class="card p-5">
        @csrf
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="font-display font-semibold">Service Controls</h2>
                <p class="text-sm text-ink-muted">Pause or resume services instantly. Paused services show a notice to customers.</p>
            </div>
            <button type="submit" class="btn-dark">Save</button>
        </div>
        <div class="flex flex-wrap gap-6 mt-4">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="bundle_enabled" value="1" {{ $bundleEnabled ? 'checked' : '' }}>
                <span class="font-medium">Data Bundles</span>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="crypto_enabled" value="1" {{ $cryptoEnabled ? 'checked' : '' }}>
                <span class="font-medium">USDT Desk</span>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="buy_enabled" value="1" {{ $buyEnabled ? 'checked' : '' }}>
                <span>Buy USDT</span>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="sell_enabled" value="1" {{ $sellEnabled ? 'checked' : '' }}>
                <span>Sell USDT</span>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="paystack_enabled" value="1" {{ $paystackEnabled ? 'checked' : '' }}>
                <span class="font-medium">Paystack Payments</span>
            </label>
        </div>
    </form>

    <div class="card overflow-x-auto">
        <div class="border-b border-border p-4 font-display font-semibold">Recent orders</div>
        <table class="w-full text-sm min-w-[700px]">
            <thead>
                <tr class="border-b border-border text-left text-ink-faint">
                    <th class="p-3 font-medium">Order</th>
                    <th class="p-3 font-medium">Customer</th>
                    <th class="p-3 font-medium">Bundle</th>
                    <th class="p-3 font-medium">Recipient</th>
                    <th class="p-3 font-medium">Charged</th>
                    <th class="p-3 font-medium">Cost</th>
                    <th class="p-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                <tr class="border-b border-border last:border-0">
                    <td class="p-3 font-mono text-xs text-ink-faint">{{ substr($o->id, 0, 8) }}</td>
                    <td class="p-3">{{ $o->user->phone ?? '—' }}</td>
                    <td class="p-3">{{ $o->product ? $o->product->network . ' ' . intval($o->product->bundle_gb) . 'GB' : 'Deleted product' }}</td>
                    <td class="p-3 font-mono">{{ $o->recipient }}</td>
                    <td class="p-3 font-mono">₵{{ number_format($o->amount_charged, 2) }}</td>
                    <td class="p-3 font-mono text-ink-faint">₵{{ number_format($o->cost_amount, 2) }}</td>
                    <td class="p-3"><span class="badge badge-{{ strtolower($o->status) }}">{{ $o->status }}</span></td>
                </tr>
                @empty
                <tr><td colspan="7" class="p-6 text-center text-ink-faint">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
