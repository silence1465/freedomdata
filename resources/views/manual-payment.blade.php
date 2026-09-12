@extends('layouts.app')
@section('title', 'Complete Payment — Freedom Data')
@section('content')
<div class="mx-auto max-w-md space-y-5">

@php
    $isCompleted    = $deposit->status === 'completed';
    $isExpired      = $deposit->status === 'expired' || ($deposit->status === 'pending' && $deposit->expires_at->isPast());
    $isManualReview = $deposit->status === 'manual_review';
    $isSent         = request()->has('sent');
@endphp

{{-- COMPLETED --}}
@if($isCompleted)
    <div class="card p-8 text-center space-y-3">
        <div class="flex justify-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full" style="background:#f0fdf4">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
        </div>
        <h1 class="font-display text-xl font-semibold">Payment confirmed!</h1>
        <p class="text-sm text-ink-muted">Your {{ $product->network }} {{ intval($product->bundle_gb) }}GB bundle for <strong>{{ $recipient }}</strong> is being processed.</p>
        @if(!empty($agentShop))
            <a href="/shop/{{ $agentCode }}/track?phone={{ $recipient }}" class="btn-gold block w-full py-3 text-base">Track your order →</a>
        @else
            <a href="/track?phone={{ $recipient }}" class="btn-gold block w-full py-3 text-base">Track your order →</a>
        @endif
    </div>

{{-- EXPIRED --}}
@elseif($isExpired)
    <div class="card p-8 text-center space-y-3">
        <p class="font-display text-lg font-semibold text-alert">Payment expired</p>
        <p class="text-sm text-ink-muted">The 2-hour window has passed. Please start a new order.</p>
        <a href="{{ !empty($agentShop) ? '/shop/'.$agentCode : '/' }}" class="btn-gold block w-full py-3">Try again</a>
    </div>

{{-- MANUAL REVIEW --}}
@elseif($isManualReview)
    <div class="card p-6 space-y-3" style="border-left:4px solid #f59e0b">
        <h1 class="font-display font-semibold">Under review</h1>
        <p class="text-sm text-ink-muted">We received a payment but need to verify it manually. Admin will process it shortly.</p>
    </div>

{{-- SENT — waiting for detection --}}
@elseif($isSent)
    <div class="card p-6 space-y-5" id="payment-wait" data-reference="{{ $deposit->payment_reference }}">
        <div class="flex items-center justify-between">
            <h1 class="font-display text-xl font-semibold">Waiting for payment</h1>
            <span class="flex items-center gap-1.5 text-xs text-ink-faint">
                <span class="h-1.5 w-1.5 rounded-full bg-signal animate-pulse"></span>
                Watching…
            </span>
        </div>

        <div class="rounded-xl bg-paper-dim px-4 py-4 text-center space-y-1">
            <p class="text-xs text-ink-muted">Reference sent</p>
            <div class="font-mono font-bold text-3xl tracking-widest text-ink">{{ $deposit->payment_reference }}</div>
            <p class="text-xs text-ink-muted">for ₵{{ number_format($deposit->expected_amount, 2) }}</p>
        </div>

        <p class="text-sm text-ink-muted text-center">This page will update automatically once we detect your payment.</p>
        <p class="text-xs text-ink-faint text-center">Expires {{ $deposit->expires_at->diffForHumans() }}</p>

        {{-- Wrong reference toggle --}}
        <div class="border-t border-border pt-4">
            <button type="button" onclick="document.getElementById('wrong-ref-form').classList.toggle('hidden'); this.classList.toggle('text-ink')" class="text-xs text-gold-dark underline w-full text-center">
                Forgot to include the reference in your payment note?
            </button>

            <div id="wrong-ref-form" class="hidden mt-4">
                @if(session('success'))
                    <div class="rounded-lg bg-signal-tint px-4 py-3 text-sm text-signal">{{ session('success') }}</div>
                @else
                <p class="text-xs text-ink-muted mb-3">Enter the details from your MoMo confirmation SMS and we'll match it manually.</p>
                <form method="POST" action="/payment/report-reference" class="space-y-3">
                    @csrf
                    <input type="hidden" name="deposit_reference" value="{{ $deposit->payment_reference }}">
                    <input type="hidden" name="expected_amount" value="{{ $deposit->expected_amount }}">
                    <div>
                        <label class="mb-1 block text-xs font-medium">Transaction ID</label>
                        <input type="text" name="transaction_id" placeholder="e.g. 87425747421" class="form-input font-mono text-sm" required>
                        <p class="text-xs text-ink-faint mt-0.5">From "Transaction ID:" in your SMS</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium">MTN Reference</label>
                        <input type="text" name="mtn_reference" placeholder="e.g. 3435" class="form-input font-mono text-sm" required>
                        <p class="text-xs text-ink-faint mt-0.5">From "Reference:" in your SMS</p>
                    </div>
                    <button type="submit" class="w-full py-2.5 text-sm font-semibold rounded-lg border border-ink text-ink hover:bg-ink hover:text-white transition">
                        Send to admin
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

{{-- PENDING — show reference code and send button --}}
@else
    <div class="card p-6 space-y-5">
        <h1 class="font-display text-xl font-semibold">Complete your payment</h1>

        {{-- Bundle summary --}}
        <div class="rounded-xl bg-paper-dim px-4 py-3 flex items-center justify-between">
            <div>
                <div class="font-display font-semibold">{{ $product->network }} {{ intval($product->bundle_gb) }}GB</div>
                <div class="text-xs text-ink-faint">To: {{ $recipient }}</div>
            </div>
            <div class="font-mono font-bold text-lg">₵{{ number_format($deposit->expected_amount, 2) }}</div>
        </div>

        {{-- Step 1 --}}
        <div class="space-y-2">
            <div class="flex items-center gap-2 text-sm font-medium">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-ink text-white text-xs font-bold">1</span>
                Send MoMo to:
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
                Type this in the <strong>Note / Reason</strong> field:
            </div>
            <div class="rounded-xl bg-paper-dim px-4 py-5 text-center">
                <div class="font-mono font-bold text-4xl tracking-widest text-ink select-all">{{ $deposit->payment_reference }}</div>
                <p class="text-xs text-ink-muted mt-2">Copy exactly into the note/reason field when sending</p>
            </div>
        </div>

        <div class="rounded-xl bg-alert-tint px-4 py-3 text-xs text-alert">
            <strong>Important:</strong> You must include <span class="font-mono font-bold">{{ $deposit->payment_reference }}</span> in the note — without it we cannot match your payment automatically.
        </div>

        {{-- Done button --}}
        <a href="?sent=1" class="btn-gold block w-full text-center py-3 text-base font-display font-semibold">
            I've sent the payment →
        </a>

        <p class="text-xs text-ink-faint text-center">Expires {{ $deposit->expires_at->diffForHumans() }}</p>
    </div>
@endif

</div>

@if($isSent && !$isCompleted && !$isExpired)
@push('scripts')
<script>
const ref = document.getElementById('payment-wait').dataset.reference;
const recipient = '{{ $recipient }}';
const agentShop = {{ !empty($agentShop) ? 'true' : 'false' }};
const agentCode = '{{ $agentCode ?? '' }}';

setInterval(async () => {
    try {
        const r = await fetch('/payment/status/' + ref);
        const d = await r.json();
        if (d.status === 'completed') {
            const trackUrl = agentShop
                ? '/shop/' + agentCode + '/track?phone=' + recipient
                : '/track?phone=' + recipient;
            window.location.href = trackUrl;
        } else if (d.status === 'expired' || d.status === 'failed' || d.status === 'manual_review') {
            location.reload();
        }
    } catch(e) {}
}, 5000);
</script>
@endpush
@endif
@endsection