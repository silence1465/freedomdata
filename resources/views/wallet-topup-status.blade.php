@extends('layouts.app')
@section('title', 'Waiting for Payment — Freedom Data')
@section('content')
<div class="mx-auto max-w-md space-y-5">

    @php
        $isPending = $deposit->status === 'pending';
        $isCompleted = $deposit->status === 'completed';
        $isExpired = $deposit->status === 'expired' || ($deposit->status === 'pending' && $deposit->expires_at->isPast());
        $isManualReview = $deposit->status === 'manual_review';
    @endphp

    @if($isCompleted)
        <div class="card p-8 text-center space-y-3">
            <div class="flex justify-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-full" style="background:#f0fdf4">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
            </div>
            <h1 class="font-display text-xl font-semibold">Payment received!</h1>
            <p class="text-sm text-ink-muted">₵{{ number_format($deposit->received_amount, 2) }} has been credited to your wallet automatically.</p>
            <a href="/wallet" class="btn-gold block w-full py-3 text-base">View your wallet</a>
        </div>

    @elseif($isExpired)
        <div class="card p-8 text-center space-y-3">
            <p class="font-display text-lg font-semibold text-alert">Payment request expired</p>
            <p class="text-sm text-ink-muted">The 2-hour window has passed. Please create a new request.</p>
            <a href="{{ route('topup.create') }}" class="btn-gold block w-full py-3 text-base">Try again</a>
        </div>

    @elseif($isManualReview)
        <div class="card p-6 space-y-3" style="border-left:4px solid #f59e0b">
            <h1 class="font-display font-semibold">Under review</h1>
            <p class="text-sm text-ink-muted">We detected a payment but need to verify it manually. Admin will credit your wallet shortly.</p>
            <a href="/wallet" class="btn-gold block text-center py-2.5 text-sm">Go to wallet</a>
        </div>

    @else
        {{-- Pending — waiting for payment --}}
        <div class="card p-6 space-y-5" id="topup-status" data-deposit-id="{{ $deposit->id }}">
            <div class="flex items-center justify-between">
                <h1 class="font-display text-xl font-semibold">Send your payment</h1>
                <span class="flex items-center gap-1.5 text-xs text-ink-faint">
                    <span class="h-1.5 w-1.5 rounded-full bg-signal animate-pulse"></span>
                    Watching for payment…
                </span>
            </div>

            {{-- Step 1 --}}
            <div class="space-y-3">
                <div class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-ink text-white text-xs font-bold">1</span>
                    <span class="font-medium text-sm">Send MoMo to this number</span>
                </div>
                <div class="rounded-xl px-4 py-3" style="background:#f0fdf4; border:1px solid #bbf7d0">
                    <div class="font-mono font-bold text-2xl text-ink">{{ $admin->phone ?? 'N/A' }}</div>
                    <div class="text-xs text-ink-faint">{{ $admin->name ?? 'Admin' }}</div>
                    <div class="text-sm font-semibold text-ink mt-1">Amount: ₵{{ number_format($deposit->expected_amount, 2) }}</div>
                </div>
            </div>

            {{-- Step 2 --}}
            <div class="space-y-3">
                <div class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-ink text-white text-xs font-bold">2</span>
                    <span class="font-medium text-sm">Type this reference in the payment note</span>
                </div>
                <div class="rounded-xl bg-paper-dim px-4 py-5 text-center">
                    <div class="font-mono font-bold text-4xl tracking-widest text-ink select-all">{{ $deposit->payment_reference }}</div>
                    <p class="text-xs text-ink-muted mt-2">Type exactly as shown in the <strong>Note / Reason</strong> field when sending MoMo</p>
                </div>
            </div>

            {{-- Step 3 --}}
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-ink text-white text-xs font-bold">3</span>
                <span class="font-medium text-sm">Your wallet is credited automatically</span>
            </div>

            <div class="rounded-xl bg-alert-tint px-4 py-3 text-xs text-alert">
                <strong>Important:</strong> You must include <span class="font-mono font-bold">{{ $deposit->payment_reference }}</span> in the payment note/reason. Without it, we cannot automatically match your payment.
            </div>

            <div class="text-xs text-ink-faint text-center">
                This request expires {{ $deposit->expires_at->diffForHumans() }}.
            </div>
        </div>
    @endif
</div>

@if($isPending)
@push('scripts')
<script>
setInterval(async () => {
    try {
        const r = await fetch('/topup/{{ $deposit->id }}/status-json');
        const data = await r.json();
        if (['completed', 'manual_review', 'expired', 'failed'].includes(data.status)) {
            location.reload();
        }
    } catch(e) {}
}, 5000);
</script>
@endpush
@endif
@endsection