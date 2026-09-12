@extends('layouts.app')
@section('title', 'Agents — Freedom Data')
@section('content')
<div class="space-y-6">
    <div>
        <h1 class="font-display text-2xl font-semibold">Agent management</h1>
        <p class="text-sm text-ink-muted">Control subscription fees, promote/demote agents, monitor their activity.</p>
    </div>
    <div class="flex gap-2 text-sm">
        <a href="/admin" class="nav-pill">Dashboard</a>
        <a href="/admin/pricing" class="nav-pill">Pricing</a>
        <a href="/admin/crypto" class="nav-pill">USDT Desk</a>
        <a href="/admin/agents" class="nav-pill active">Agents</a>
        <a href="/admin/payouts" class="nav-pill {{ request()->is('admin/payouts') ? 'active' : '' }} whitespace-nowrap">Payouts</a>
        <a href="/admin/special-pricing" class="nav-pill {{ request()->is('admin/special-pricing') ? 'active' : '' }} whitespace-nowrap">special Pricing</a>
        <a href="/admin/sms-devices" class="nav-pill {{ request()->is('admin/sms-devices') ? 'active' : '' }} whitespace-nowrap">sms-devices</a>
    </div>

    {{-- Subscription fee controls --}}
    <form method="POST" action="/admin/agents/plan" class="card p-5 space-y-4">
        @csrf
        <h2 class="font-display font-semibold">Subscription fees</h2>
        <p class="text-sm text-ink-muted">Customers pay these fees from their wallet to become agents.</p>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium">Monthly fee (GHS)</label>
                <input name="monthly_fee" value="{{ $plan->monthly_fee }}" class="form-input font-mono" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Yearly fee (GHS)</label>
                <input name="yearly_fee" value="{{ $plan->yearly_fee }}" class="form-input font-mono" required>
            </div>
            <div class="flex items-end gap-3">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_enabled" value="1" {{ $plan->is_enabled ? 'checked' : '' }}>
                    Accept new subscriptions
                </label>
            </div>
        </div>
        <button type="submit" class="btn-dark">Save fees</button>
    </form>

    {{-- Pending topup requests --}}
    @if($topupRequests->count())
    <div class="card overflow-x-auto">
        <div class="border-b border-border p-4 flex items-center gap-2 font-display font-semibold">
            Wallet Topup Requests
            <span class="rounded-full bg-alert text-white text-xs font-bold px-2 py-0.5">{{ $topupRequests->count() }}</span>
        </div>
        <table class="w-full text-sm min-w-[500px]">
            <thead>
                <tr class="border-b border-border text-left text-ink-faint">
                    <th class="p-3 font-medium">User</th>
                    <th class="p-3 font-medium">Amount</th>
                    <th class="p-3 font-medium">Transaction ID</th>
                    <th class="p-3 font-medium">MTN Ref</th>
                    <th class="p-3 font-medium">Sender</th>
                    <th class="p-3 font-medium">Time</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($topupRequests as $req)
                <tr class="border-b border-border last:border-0 bg-gold-tint/20">
                    <td class="p-3">
                        <div class="font-medium">{{ $req->user->name ?? 'Guest' }}</div>
                        <div class="text-xs text-ink-faint font-mono">{{ $req->user->phone ?? 'No account' }}</div>
                    </td>
                    <td class="p-3 font-mono font-semibold">₵{{ number_format($req->amount, 2) }}</td>
                    <td class="p-3 font-mono text-sm">{{ $req->transaction_id }}</td>
                    <td class="p-3 font-mono text-sm">{{ $req->mtn_reference }}</td>
                    <td class="p-3 text-sm">{{ $req->sender_name ?? '—' }}</td>
                    <td class="p-3 text-xs text-ink-faint">{{ $req->created_at->diffForHumans() }}</td>
                    <td class="p-3">
                        <div class="flex gap-2">
                            <form method="POST" action="/admin/topup-requests/{{ $req->id }}/approve">
                                @csrf
                                <button class="btn-signal text-xs" onclick="return confirm('Verify TxnID {{ $req->transaction_id }} and credit ₵{{ number_format($req->amount, 2) }}?')">Approve</button>
                            </form>
                            <form method="POST" action="/admin/topup-requests/{{ $req->id }}/reject">
                                @csrf
                                <button class="btn-alert text-xs">Reject</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Fund agent wallet --}}
    <form method="POST" action="/admin/agents/fund" class="card p-5 space-y-3">
        @csrf
        <h2 class="font-display font-semibold">Fund agent wallet</h2>
        <p class="text-sm text-ink-muted">Directly credit an agent's wallet balance.</p>
        <div class="grid gap-3 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium">Select agent</label>
                <select name="user_id" class="form-input" required>
                    <option value="">Choose agent...</option>
                    @foreach($agents as $a)
                        <option value="{{ $a->id }}">{{ $a->name ?? 'No name' }} — {{ $a->phone }} (₵{{ number_format($a->wallet_balance, 2) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Amount (GHS)</label>
                <input type="number" name="amount" min="1" step="0.01" placeholder="0.00" class="form-input font-mono" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Note (optional)</label>
                <input type="text" name="note" placeholder="e.g. Top up for March" class="form-input">
            </div>
        </div>
        <button type="submit" class="btn-gold" onclick="return confirm('Confirm adding funds to this agent wallet?')">Fund wallet</button>
    </form>

    {{-- Manually promote --}}
    <form method="POST" action="/admin/agents/promote" class="card p-5">
        @csrf
        <h2 class="font-display mb-3 font-semibold">Manually promote (skip payment)</h2>
        <div class="flex gap-3 items-end">
            <div class="flex-1">
                <label class="mb-1 block text-sm font-medium">Select customer</label>
                <select name="user_id" class="form-input" required>
                    <option value="">Choose a customer...</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->name ?? 'No name' }} — {{ $c->phone }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-gold whitespace-nowrap">Make agent</button>
        </div>
    </form>

    {{-- Current agents --}}
    <div class="card overflow-x-auto">
        <div class="border-b border-border p-4 font-display font-semibold">Active agents ({{ $agents->count() }})</div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border text-left text-ink-faint">
                    <th class="p-3 font-medium">Name</th>
                    <th class="p-3 font-medium">Phone</th>
                    <th class="p-3 font-medium">Shop link</th>
                    <th class="p-3 font-medium">Wallet</th>
                    <th class="p-3 font-medium">Expires</th>
                    <th class="p-3 font-medium">Orders</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($agents as $a)
                <tr class="border-b border-border last:border-0">
                    <td class="p-3 font-medium">{{ $a->name ?? '—' }}</td>
                    <td class="p-3 font-mono">{{ $a->phone }}</td>
                    <td class="p-3">
                        @if($a->agent_code)
                            <a href="/shop/{{ $a->agent_code }}" target="_blank" class="text-gold-dark underline text-xs font-mono">{{ url('/shop/' . $a->agent_code) }}</a>
                        @else
                            <span class="text-ink-faint text-xs">No code</span>
                        @endif
                    </td>
                    <td class="p-3 font-mono">₵{{ number_format($a->wallet_balance, 2) }}</td>
                    <td class="p-3 {{ $a->agent_expires_at && $a->agent_expires_at->isPast() ? 'text-alert' : '' }}">
                        {{ $a->agent_expires_at ? $a->agent_expires_at->format('M d, Y') : 'No expiry set' }}
                    </td>
                    <td class="p-3">{{ $a->orders()->count() }}</td>
                    <td class="p-3">
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="/admin/agents/{{ $a->id }}/extend" class="flex gap-1">
                                @csrf
                                <input type="number" name="days" value="30" min="1" max="3650" class="form-input w-20 py-1 px-2 font-mono text-xs" aria-label="Extension days" required>
                                <button class="btn-signal text-xs" onclick="return confirm('Extend {{ $a->name ?? $a->phone }} subscription?')">Extend</button>
                            </form>
                            <form method="POST" action="/admin/agents/{{ $a->id }}/demote" onsubmit="return confirm('Demote {{ $a->name ?? $a->phone }}?')">
                                @csrf
                                <button class="btn-alert">Demote</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="p-6 text-center text-ink-faint">No agents yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
