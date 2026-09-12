@extends('layouts.app')
@section('title', 'Top Up Wallet — Freedom Data')
@section('content')
<div class="mx-auto max-w-md space-y-6">
    <div>
        <h1 class="font-display text-2xl font-semibold">Top up your wallet</h1>
        <p class="text-sm text-ink-muted mt-1">Enter the amount, get a reference code, send MoMo — done automatically.</p>
    </div>

    @if($deposit && $deposit->status === 'pending' && $deposit->expires_at->isFuture())
        {{-- Active deposit — show instructions --}}
        <div class="card p-6 space-y-5">
            <div class="flex items-center justify-between">
                <h2 class="font-display font-semibold">Send your payment</h2>
                <span class="text-xs text-ink-faint">Expires {{ $deposit->expires_at->diffForHumans() }}</span>
            </div>

            {{-- Step 1 --}}
            <div class="space-y-2">
                <div class="flex items-center gap-2 text-sm font-medium">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-ink text-white text-xs font-bold">1</span>
                    Open MTN MoMo and send to:
                </div>
                <div class="rounded-xl px-4 py-3" style="background:#f0fdf4; border:1px solid #bbf7d0">
                    <div class="font-mono font-bold text-2xl text-ink">{{ $admin->phone }}</div>
                    <div class="text-xs text-ink-faint">{{ $admin->name }}</div>
                    <div class="font-semibold text-ink mt-1">Amount: ₵{{ number_format($deposit->expected_amount, 2) }}</div>
                </div>
            </div>

            {{-- Step 2 --}}
            <div class="space-y-2">
                <div class="flex items-center gap-2 text-sm font-medium">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-ink text-white text-xs font-bold">2</span>
                    Type this code in the <strong>Reason / Note</strong> field:
                </div>
                <div class="rounded-xl bg-paper-dim px-4 py-5 text-center">
                    <div class="font-mono font-bold text-4xl tracking-widest text-ink select-all">{{ $deposit->payment_reference }}</div>
                    <p class="text-xs text-ink-muted mt-2">Copy exactly as shown into the reason/note field when sending</p>
                </div>
            </div>

            {{-- Step 3 --}}
            <div class="flex items-center gap-2 text-sm font-medium">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-ink text-white text-xs font-bold">3</span>
                Your wallet is credited automatically once we detect your payment
            </div>

            <div class="rounded-xl bg-alert-tint px-4 py-3 text-xs text-alert">
                <strong>Must include the code</strong> — without <span class="font-mono font-bold">{{ $deposit->payment_reference }}</span> in the note, we cannot match your payment automatically.
            </div>

            <a href="{{ route('topup.status', $deposit->id) }}" class="btn-gold block w-full text-center py-3">
                I've sent the payment — check status →
            </a>
        </div>

        <div class="text-center">
            <p class="text-xs text-ink-faint mb-2">Wrong amount? Generate a new one:</p>
        </div>
    @endif

    {{-- Generate new reference --}}
    <form method="POST" action="{{ route('topup.create') }}" class="card p-5 space-y-4">
        @csrf
        <h2 class="font-display font-semibold">{{ $deposit ? 'New amount' : 'How much to top up?' }}</h2>
        <div>
            <label class="mb-1 block text-sm font-medium">Amount (GHS)</label>
            <input type="number" name="amount" min="1" step="0.01" placeholder="e.g. 50.00" class="form-input font-mono text-lg" required>
        </div>
        <button type="submit" class="btn-gold w-full py-3 text-base">Get payment reference →</button>
        <p class="text-xs text-ink-faint text-center">You'll receive a unique code to include in your MoMo payment so we can verify it automatically.</p>
    </form>
</div>
@endsection
