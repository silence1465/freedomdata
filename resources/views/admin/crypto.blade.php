@extends('layouts.app')
@section('title', 'USDT Desk — Freedom Data')
@section('content')
<div class="space-y-6">
    <div>
        <h1 class="font-display text-2xl font-semibold">USDT Desk</h1>
        <p class="text-sm text-ink-muted">Set rates, confirm/cancel orders manually.</p>
    </div>
    <div class="flex gap-2 text-sm">
        <a href="/admin" class="nav-pill">Dashboard</a>
        <a href="/admin/pricing" class="nav-pill">Pricing</a>
        <a href="/admin/crypto" class="nav-pill active">USDT Desk</a>
        <a href="/admin/agents" class="nav-pill">Agents</a>
        <a href="/admin/payouts" class="nav-pill {{ request()->is('admin/payouts') ? 'active' : '' }} whitespace-nowrap">Payouts</a>
        <a href="/admin/special-pricing" class="nav-pill {{ request()->is('admin/special-pricing') ? 'active' : '' }} whitespace-nowrap">special Pricing</a>
        <a href="/admin/sms-devices" class="nav-pill {{ request()->is('admin/sms-devices') ? 'active' : '' }} whitespace-nowrap">sms-devices</a>
    </div>

    {{-- Settings --}}
    <form method="POST" action="/admin/crypto/settings" class="card p-5 space-y-4">
        @csrf
        <h2 class="font-display font-semibold">Rate settings</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium">Buy rate (₵/USDT)</label>
                <input name="buy_rate" value="{{ $settings->buy_rate }}" class="form-input font-mono" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Sell rate (₵/USDT)</label>
                <input name="sell_rate" value="{{ $settings->sell_rate }}" class="form-input font-mono" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Network</label>
                <select name="network" class="form-input">
                    @foreach(['TRC20','ERC20','BEP20'] as $n)
                        <option value="{{ $n }}" {{ $settings->network === $n ? 'selected' : '' }}>{{ $n }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Deposit address</label>
                <input name="wallet_address" value="{{ $settings->wallet_address }}" class="form-input font-mono text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Min order (GHS)</label>
                <input name="min_ghs" value="{{ $settings->min_ghs }}" class="form-input font-mono" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Max order (GHS)</label>
                <input name="max_ghs" value="{{ $settings->max_ghs }}" class="form-input font-mono" required>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-5 pt-2">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_enabled" value="1" {{ $settings->is_enabled ? 'checked' : '' }}>
                <span class="font-medium">Desk enabled</span>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="buy_enabled" value="1" {{ $settings->buy_enabled ? 'checked' : '' }}>
                <span>Buy USDT <span class="text-ink-faint">(customer pays GHS)</span></span>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="sell_enabled" value="1" {{ $settings->sell_enabled ? 'checked' : '' }}>
                <span>Sell USDT <span class="text-ink-faint">(customer sends USDT)</span></span>
            </label>
            <button type="submit" class="btn-dark ml-auto">Save settings</button>
        </div>
    </form>

    {{-- Pending orders --}}
    @if($pending->count())
    <div>
        <h2 class="font-display font-semibold text-alert">⚠ {{ $pending->count() }} orders need action</h2>
    </div>
    @endif

    {{-- All orders --}}
    <div class="card overflow-x-auto">
        <div class="border-b border-border p-4 font-display font-semibold">All crypto orders</div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border text-left text-ink-faint">
                    <th class="p-3 font-medium">Type</th>
                    <th class="p-3 font-medium">Customer</th>
                    <th class="p-3 font-medium">GHS</th>
                    <th class="p-3 font-medium">USDT</th>
                    <th class="p-3 font-medium">Address / Tx</th>
                    <th class="p-3 font-medium">Status</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                <tr class="border-b border-border last:border-0">
                    <td class="p-3 font-medium">{{ $o->type }}</td>
                    <td class="p-3 font-mono text-xs">{{ $o->user->phone ?? '—' }}</td>
                    <td class="p-3 font-mono">₵{{ number_format($o->ghs_amount, 2) }}</td>
                    <td class="p-3 font-mono">{{ $o->usdt_amount }}</td>
                    <td class="p-3 max-w-[180px] truncate font-mono text-xs" title="{{ $o->customer_wallet_address ?? $o->tx_hash }}">
                        {{ $o->customer_wallet_address ?? $o->tx_hash ?? '—' }}
                    </td>
                    <td class="p-3"><span class="badge badge-{{ strtolower($o->status === 'AWAITING_CONFIRMATION' ? 'pending' : $o->status) }}">{{ str_replace('_', ' ', $o->status) }}</span></td>
                    <td class="p-3">
                        @if(in_array($o->status, ['PENDING', 'AWAITING_CONFIRMATION']))
                        <div class="flex gap-2">
                            <form method="POST" action="/admin/crypto/{{ $o->id }}/confirm">@csrf <button class="btn-signal">Confirm</button></form>
                            <form method="POST" action="/admin/crypto/{{ $o->id }}/cancel">@csrf <button class="btn-alert">Cancel</button></form>
                        </div>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="p-6 text-center text-ink-faint">No crypto orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
