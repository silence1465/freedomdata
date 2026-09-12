@extends('layouts.app')
@section('title', 'SMS Devices — Freedom Data')
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

    {{-- Register new device --}}
    <form method="POST" action="/admin/sms-devices" class="card p-5 space-y-4">
        @csrf
        <h2 class="font-display font-semibold">Register Android device</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Device name</label>
                <input type="text" name="device_name" placeholder="e.g. MoMo Phone" class="form-input" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Device ID</label>
                <input type="text" name="device_identifier" placeholder="e.g. phone001" class="form-input" required>
            </div>
        </div>
        <button type="submit" class="btn-gold">Generate token</button>
    </form>

    {{-- Show token once after registration --}}
    @if(session('device_token'))
    <div class="card p-5 space-y-3" style="border:2px solid #16a34a">
        <h2 class="font-display font-semibold text-signal">✓ Device registered — save this token now!</h2>
        <p class="text-sm text-ink-muted">This token will <strong>never be shown again</strong>. Copy it into the SMS Gateway app.</p>
        <div class="rounded-xl bg-paper-dim px-4 py-3 font-mono text-sm break-all select-all">{{ session('device_token') }}</div>
        <div class="rounded-xl bg-ink text-white px-4 py-3 text-sm space-y-1">
            <div class="font-semibold mb-2">Configure the app with:</div>
            <div>Sender: <span class="text-gold">Mobile Money</span></div>
            <div>URL: <span class="text-gold">{{ url('/api/sms/receive') }}</span></div>
            <div>Header: <span class="text-gold">X-Device-Token: {{ session('device_token') }}</span></div>
        </div>
    </div>
    @endif

    {{-- Existing devices --}}
    @if($devices->count())
    <div class="card overflow-x-auto">
        <div class="border-b border-border p-4 font-display font-semibold">Registered devices</div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border text-left text-ink-faint">
                    <th class="p-3 font-medium">Name</th>
                    <th class="p-3 font-medium">ID</th>
                    <th class="p-3 font-medium">Last seen</th>
                    <th class="p-3 font-medium">Status</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($devices as $d)
                <tr class="border-b border-border last:border-0">
                    <td class="p-3 font-medium">{{ $d->device_name }}</td>
                    <td class="p-3 font-mono text-xs">{{ $d->device_identifier }}</td>
                    <td class="p-3 text-xs text-ink-faint">{{ $d->last_seen_at ? $d->last_seen_at->diffForHumans() : 'Never' }}</td>
                    <td class="p-3">
                        @if($d->is_active)
                            <span class="rounded-full bg-signal-tint text-signal text-xs px-2 py-0.5">Active</span>
                        @else
                            <span class="rounded-full bg-alert-tint text-alert text-xs px-2 py-0.5">Inactive</span>
                        @endif
                    </td>
                    <td class="p-3">
                        <form method="POST" action="/admin/sms-devices/{{ $d->id }}/toggle">
                            @csrf
                            <button class="text-xs text-ink-faint underline">{{ $d->is_active ? 'Deactivate' : 'Activate' }}</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
