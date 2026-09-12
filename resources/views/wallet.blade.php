@extends('layouts.app')
@section('title', 'Wallet — Freedom Data')
@section('content')
<div class="mx-auto max-w-2xl">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl font-semibold">Wallet</h1>
            <p class="font-display mt-1 text-3xl font-bold text-signal">₵{{ number_format($user->wallet_balance, 2) }}</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
            {{-- Paystack topup or manual payment --}}
            @if($paystackEnabled)
            <form method="POST" action="/wallet/topup/paystack" class="flex items-center gap-2">
                @csrf
                <input type="number" name="amount" placeholder="Amount" min="1" step="0.01" class="form-input w-28 font-mono text-sm" required>
                <button class="btn-gold text-sm whitespace-nowrap">Top up via MoMo</button>
            </form>
            @else
            <a href="/wallet/topup/create" class="btn-gold text-sm whitespace-nowrap">Top up wallet</a>
            @endif

            {{-- Manual topup (admin only) --}}
            @if(auth()->user()->isAdmin())
            <form method="POST" action="/wallet/topup" class="flex items-center gap-2">
                @csrf
                <input type="number" name="amount" placeholder="Amount" min="1" step="0.01" class="form-input w-28 font-mono text-sm" required>
                <button class="btn-dark text-sm whitespace-nowrap">Manual add</button>
            </form>
            @endif
        </div>
    </div>

    <div class="card overflow-x-auto">
        <div class="border-b border-border p-4 font-display font-semibold">Transaction history</div>
        <table class="w-full text-sm min-w-[500px]">
            <thead>
                <tr class="border-b border-border text-left text-ink-faint">
                    <th class="p-3 font-medium">Date</th>
                    <th class="p-3 font-medium">Type</th>
                    <th class="p-3 font-medium">Amount</th>
                    <th class="p-3 font-medium">Balance after</th>
                    <th class="p-3 font-medium">Ref</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $e)
                <tr class="border-b border-border last:border-0">
                    <td class="p-3 text-ink-faint">{{ $e->created_at->format('M d, H:i') }}</td>
                    <td class="p-3"><span class="badge {{ $e->amount >= 0 ? 'badge-delivered' : 'badge-pending' }}">{{ $e->type }}</span></td>
                    <td class="p-3 font-mono {{ $e->amount >= 0 ? 'text-signal' : 'text-alert' }}">{{ $e->amount >= 0 ? '+' : '' }}{{ number_format($e->amount, 2) }}</td>
                    <td class="p-3 font-mono">{{ number_format($e->balance_after, 2) }}</td>
                    <td class="p-3 text-ink-faint truncate max-w-[120px]">{{ $e->reference ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="p-6 text-center text-ink-faint">No transactions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
