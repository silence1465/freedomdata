@extends('layouts.app')
@section('title', 'Request Payout — Freedom Data')
@section('content')
<div class="mx-auto max-w-lg">
    <h1 class="font-display text-2xl font-semibold mb-1">Request Payout</h1>
    <p class="text-sm text-ink-muted mb-6">Withdraw your earnings to your mobile money account.</p>

    <div class="grid gap-4 sm:grid-cols-2 mb-6">
        <div class="stat-card">
            <div class="text-xs text-ink-faint">Available balance</div>
            <div class="font-display mt-1 text-2xl font-semibold text-signal">₵{{ number_format($user->wallet_balance, 2) }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs text-ink-faint">Registered number</div>
            <div class="font-display mt-1 text-lg font-semibold font-mono">{{ $user->phone }}</div>
        </div>
    </div>

    @if($pendingPayout)
        <div class="card p-5">
            <div class="flex items-center gap-3 mb-2">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--color-gold)"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span class="font-display font-semibold">Pending payout</span>
            </div>
            <div class="rounded-xl bg-gold-tint px-4 py-3 text-sm space-y-1">
                <div class="flex justify-between"><span class="text-ink-muted">Amount</span><span class="font-mono font-semibold">₵{{ number_format($pendingPayout->amount, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-ink-muted">Network</span><span>{{ $pendingPayout->provider }}</span></div>
                <div class="flex justify-between"><span class="text-ink-muted">To</span><span class="font-mono">{{ $pendingPayout->account_number }}</span></div>
                <div class="flex justify-between"><span class="text-ink-muted">Submitted</span><span>{{ $pendingPayout->created_at->diffForHumans() }}</span></div>
            </div>
            <p class="text-xs text-ink-faint mt-3">You will be notified once your payout is processed.</p>
        </div>
    @else
        <form method="POST" action="/payout" class="card p-5 space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium">Payout amount (GHS)</label>
                <input type="number" name="amount" min="10" max="{{ $user->wallet_balance }}" step="0.01" placeholder="Min ₵10.00" class="form-input font-mono text-lg" required>
                <p class="text-xs text-ink-faint mt-1">Maximum: ₵{{ number_format($user->wallet_balance, 2) }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">MoMo network</label>
                <select name="provider" class="form-input" required>
                    <option value="">Select network...</option>
                    <option value="MTN MoMo">MTN MoMo</option>
                    <option value="Telecel Cash">Telecel Cash</option>
                    <option value="AirtelTigo Money">AirtelTigo Money</option>
                </select>
            </div>

            <div class="rounded-xl bg-paper-dim px-4 py-3 text-sm space-y-1">
                <div class="flex justify-between"><span class="text-ink-muted">Account name</span><span class="font-medium">{{ $user->name }}</span></div>
                <div class="flex justify-between"><span class="text-ink-muted">Account number</span><span class="font-mono">{{ $user->phone }}</span></div>
                <p class="text-xs text-ink-faint mt-2">Payout will be sent to the phone number you registered with.</p>
            </div>

            <button type="submit" class="btn-gold w-full py-3 text-base">Submit payout request</button>
        </form>
    @endif

    {{-- Payout history --}}
    @if($payouts->count())
    <div class="mt-8">
        <h2 class="font-display font-semibold mb-3">Payout history</h2>
        <div class="space-y-2">
            @foreach($payouts as $p)
                @php
                    $statusClass = match($p->status) {
                        'PAID' => 'badge-delivered',
                        'REJECTED' => 'badge-failed',
                        default => 'badge-pending',
                    };
                @endphp
                <div class="card p-3 flex items-center justify-between">
                    <div>
                        <span class="font-mono font-semibold">₵{{ number_format($p->amount, 2) }}</span>
                        <span class="text-sm text-ink-faint ml-2">{{ $p->provider }} · {{ $p->created_at->format('M d, Y') }}</span>
                    </div>
                    <span class="badge {{ $statusClass }}">{{ $p->status }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
