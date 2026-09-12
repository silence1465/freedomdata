@extends('layouts.app')
@section('title', 'Payouts — Freedom Data')
@section('content')
<div class="space-y-6">
    <div>
        <h1 class="font-display text-2xl font-semibold">Agent Payouts</h1>
        <p class="text-sm text-ink-muted">Review and process agent withdrawal requests.</p>
    </div>
    <div class="flex gap-2 text-sm overflow-x-auto">
        <a href="/admin" class="nav-pill whitespace-nowrap">Dashboard</a>
        <a href="/admin/pricing" class="nav-pill whitespace-nowrap">Pricing</a>
        <a href="/admin/crypto" class="nav-pill whitespace-nowrap">USDT Desk</a>
        <a href="/admin/agents" class="nav-pill whitespace-nowrap">Agents</a>
        <a href="/admin/payouts" class="nav-pill active whitespace-nowrap">Payouts {{ $pendingCount > 0 ? "({$pendingCount})" : '' }}</a>
        <a href="/admin/special-pricing" class="nav-pill whitespace-nowrap">Special Pricing</a>
        <a href="/admin/sms-devices" class="nav-pill {{ request()->is('admin/sms-devices') ? 'active' : '' }} whitespace-nowrap">sms-devices</a>
    </div>

    <div class="card overflow-x-auto">
        <div class="border-b border-border p-4 font-display font-semibold">
            All payout requests
            @if($pendingCount > 0)
                <span class="ml-2 badge badge-pending">{{ $pendingCount }} pending</span>
            @endif
        </div>
        <table class="w-full text-sm min-w-[700px]">
            <thead>
                <tr class="border-b border-border text-left text-ink-faint">
                    <th class="p-3 font-medium">Agent</th>
                    <th class="p-3 font-medium">Amount</th>
                    <th class="p-3 font-medium">Method</th>
                    <th class="p-3 font-medium">Account</th>
                    <th class="p-3 font-medium">Status</th>
                    <th class="p-3 font-medium">Date</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($payouts as $p)
                @php
                    $statusClass = match($p->status) {
                        'PAID' => 'badge-delivered',
                        'REJECTED' => 'badge-failed',
                        default => 'badge-pending',
                    };
                @endphp
                <tr class="border-b border-border last:border-0 {{ $p->isPending() ? 'bg-gold-tint/20' : '' }}">
                    <td class="p-3">
                        <div class="font-medium">{{ $p->user->name ?? '—' }}</div>
                        <div class="text-xs text-ink-faint font-mono">{{ $p->user->phone }}</div>
                    </td>
                    <td class="p-3 font-mono font-semibold">₵{{ number_format($p->amount, 2) }}</td>
                    <td class="p-3">{{ ucfirst($p->method) }}</td>
                    <td class="p-3">
                        <div class="font-medium">{{ $p->account_name }}</div>
                        <div class="text-xs text-ink-faint font-mono">{{ $p->provider }} — {{ $p->account_number }}</div>
                    </td>
                    <td class="p-3"><span class="badge {{ $statusClass }}">{{ $p->status }}</span></td>
                    <td class="p-3 text-xs text-ink-faint">{{ $p->created_at->diffForHumans() }}</td>
                    <td class="p-3">
                        @if($p->isPending())
                            <div class="flex gap-2">
                                <form method="POST" action="/admin/payouts/{{ $p->id }}/approve">
                                    @csrf
                                    <button class="btn-signal text-xs" onclick="return confirm('Confirm you have sent ₵{{ number_format($p->amount, 2) }} to {{ $p->account_name }}?')">Paid</button>
                                </form>
                                <form method="POST" action="/admin/payouts/{{ $p->id }}/reject">
                                    @csrf
                                    <button class="btn-alert text-xs" onclick="return confirm('Reject and refund ₵{{ number_format($p->amount, 2) }} to agent wallet?')">Reject</button>
                                </form>
                            </div>
                        @endif
                        @if($p->admin_notes)
                            <div class="text-xs text-ink-faint mt-1">{{ $p->admin_notes }}</div>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="p-6 text-center text-ink-faint">No payout requests yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($payouts->hasPages())
        <div class="flex items-center justify-center gap-1">
            @if($payouts->onFirstPage())
                <span class="px-3 py-2 text-sm text-ink-faint">← Previous</span>
            @else
                <a href="{{ $payouts->previousPageUrl() }}" class="nav-pill text-sm">← Previous</a>
            @endif
            <span class="px-3 py-2 text-sm text-ink-muted">Page {{ $payouts->currentPage() }} of {{ $payouts->lastPage() }}</span>
            @if($payouts->hasMorePages())
                <a href="{{ $payouts->nextPageUrl() }}" class="nav-pill text-sm">Next →</a>
            @else
                <span class="px-3 py-2 text-sm text-ink-faint">Next →</span>
            @endif
        </div>
    @endif
</div>
@endsection
