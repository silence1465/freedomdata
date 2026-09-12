@extends('layouts.app')
@section('title', 'Track Order — Freedom Data')
@section('content')
<div class="mx-auto max-w-2xl">
    <h1 class="font-display text-2xl font-semibold mb-1">Track your orders</h1>
    <p class="text-sm text-ink-muted mb-6">Enter the recipient phone number to view all orders sent to that number.</p>

    <form method="GET" action="/track" class="flex gap-2 mb-8">
        <input type="tel" name="phone" value="{{ $phone ?? '' }}" placeholder="0241234567" class="form-input font-mono flex-1" inputmode="numeric" required>
        <button type="submit" class="btn-gold whitespace-nowrap px-6">Search</button>
    </form>
    
    @php
        $pendingDeposit = \App\Models\SmsDeposit::where('status', 'pending')
            ->where('expires_at', '>', now())
            ->whereJsonContains('purpose_meta->recipient', $phone ?? '')
            ->first();
    @endphp
    
    @if($pendingDeposit)
        <div class="card p-5" style="border-left:4px solid #C8A84B">
            <div class="font-semibold mb-1">⏳ Payment pending</div>
            <p class="text-sm text-ink-muted">Waiting for ₵{{ number_format($pendingDeposit->expected_amount, 2) }} payment. Bundle will dispatch automatically once received.</p>
            <div class="text-xs text-ink-faint mt-2">Ref: <span class="font-mono font-bold">{{ $pendingDeposit->payment_reference }}</span></div>
        </div>
    @endif

    @if($phone && $orders !== null)
        @if($orders->total() > 0)
            <div class="mb-4 text-sm text-ink-muted">
                {{ $orders->total() }} {{ $orders->total() === 1 ? 'order' : 'orders' }} found for
                <span class="font-mono font-semibold text-ink">{{ $phone }}</span>
            </div>

            <div class="space-y-3">
                @foreach($orders as $o)
                    @php
                        $statusColors = [
                            'DELIVERED' => ['bg-signal-tint', 'text-signal'],
                            'PENDING' => ['bg-gold-tint', 'text-gold-dark'],
                            'PROCESSING' => ['bg-gold-tint', 'text-gold-dark'],
                            'FAILED' => ['bg-alert-tint', 'text-alert'],
                            'REFUNDED' => ['bg-paper-dim', 'text-ink-muted'],
                        ];
                        $sc = $statusColors[$o->status] ?? ['bg-paper-dim', 'text-ink-muted'];
                    @endphp
                    <a href="/track/{{ $o->id }}" class="card p-4 block hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <x-network-logo :network="$o->product->network" size="sm" />
                                <span class="font-display font-semibold">{{ $o->product->network }} {{ intval($o->product->bundle_gb) }}GB</span>
                            </div>
                            <span class="badge {{ $sc[0] }} {{ $sc[1] }}">{{ $o->status }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <div class="text-ink-faint">
                                <span class="font-mono">₵{{ number_format($o->amount_charged, 2) }}</span>
                                <span class="mx-1">·</span>
                                {{ $o->created_at->format('M d, Y · h:i A') }}
                            </div>
                            @if($o->status === 'DELIVERED')
                                <span class="text-signal text-xs font-medium flex items-center gap-1">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    Delivered
                                </span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            @if($orders->hasPages())
                <div class="mt-6 flex items-center justify-center gap-1">
                    @if($orders->onFirstPage())
                        <span class="px-3 py-2 text-sm text-ink-faint">← Previous</span>
                    @else
                        <a href="{{ $orders->previousPageUrl() }}" class="nav-pill text-sm">← Previous</a>
                    @endif
                    <span class="px-3 py-2 text-sm text-ink-muted">Page {{ $orders->currentPage() }} of {{ $orders->lastPage() }}</span>
                    @if($orders->hasMorePages())
                        <a href="{{ $orders->nextPageUrl() }}" class="nav-pill text-sm">Next →</a>
                    @else
                        <span class="px-3 py-2 text-sm text-ink-faint">Next →</span>
                    @endif
                </div>
            @endif
        @else
            <div class="card p-8 text-center">
                <svg class="mx-auto mb-3" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-ink-faint)"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <p class="font-display font-semibold">No orders found</p>
                <p class="mt-1 text-sm text-ink-muted">No orders found for <span class="font-mono">{{ $phone }}</span>. Check the number and try again.</p>
            </div>
        @endif
    @endif
</div>
@endsection
