@extends('layouts.app')
@section('title', 'Order Tracking — Freedom Data')
@section('content')
@php
    $isFailure = in_array($order->status, ['FAILED','REFUNDED','REFUND_PROCESSING']);
    $isOnHold = $order->status === 'ON_HOLD';
    $steps = ['PENDING' => 1, 'PROCESSING' => 2, 'ON_HOLD' => 2, 'DELIVERED' => 4];
    $lit = $steps[$order->status] ?? 0;
    $statusLabels = [
        'PENDING'    => ['Order received', 'We have charged your wallet and queued the order.'],
        'PROCESSING' => [optional($order)->datasika_raw_status ?? 'Dispatching', 'Your bundle has been sent to the network. Waiting for confirmation.'],
        'ON_HOLD'    => [optional($order)->datasika_raw_status ?? 'On Hold', 'Your order is being reviewed before delivery.'],
        'DELIVERED'  => ['Delivered!', 'The bundle has landed on the recipient\'s line.'],
        'FAILED'     => ['Delivery failed', 'The network could not complete this dispatch.'],
        'REFUNDED'   => ['Refunded', 'The charge has been credited back to your wallet.'],
    ];
    $label = $statusLabels[$order->status] ?? ['Processing', ''];
@endphp

<div class="mx-auto max-w-lg space-y-5" id="order-status" data-order-id="{{ $order->id }}">
    <div class="flex items-center justify-between">
        <h1 class="font-display text-xl font-semibold">Order tracking</h1>
        @unless(in_array($order->status, ['DELIVERED','FAILED','REFUNDED']))
            <span class="flex items-center gap-1.5 text-xs text-ink-faint">
                <span class="h-1.5 w-1.5 rounded-full bg-signal animate-pulse"></span> Checking…
            </span>
        @endunless
    </div>

    <div class="card p-6">
        <div class="flex items-center gap-4">
            <span class="signal-bars">
                @for($i = 0; $i < 4; $i++)
                    @if($isFailure)
                        <span class="bar {{ $i === 0 ? 'lit' : '' }}" style="{{ $i === 0 ? 'background:var(--color-alert)' : 'background:var(--color-alert-tint)' }}"></span>
                    @elseif($isOnHold)
                        <span class="bar {{ $i < 2 ? 'lit' : '' }}" style="{{ $i < 2 ? 'background:#f59e0b' : '' }}"></span>
                    @else
                        <span class="bar {{ $i < $lit ? 'lit' : '' }}"></span>
                    @endif
                @endfor
            </span>
            <div>
                <div class="font-display text-base font-semibold" id="status-badge">{{ $label[0] }}</div>
                <div class="text-sm text-ink-muted">{{ $label[1] }}</div>
            </div>
        </div>

        {{-- DataSika message box --}}
        @if($isOnHold)
            <div class="mt-4 rounded-xl px-4 py-3 text-sm" style="background:#fffbeb; border:1px solid #fde68a">
                <div class="font-semibold mb-2 text-amber-700">On Hold — verifying this number with MTN</div>
                <p class="text-ink-muted leading-relaxed">
                    Your order <strong>has not failed</strong>. MTN has not accepted this number yet — it temporarily returned <strong>"Beneficiary not allowed"</strong>, so the number is being verified before your bundle is sent. This usually takes <strong>2 to 4 days</strong>, and in rare cases up to a week. You do not need to do anything while it is under review. Once it is verified, your bundle is delivered <strong>automatically</strong>, and future orders to this same number should go through normally.
                </p>
            </div>
        @elseif($order->datasika_message)
            <div class="mt-4 rounded-xl px-4 py-3 text-sm"
                 style="{{ $isFailure ? 'background:#fef2f2; border:1px solid #fecaca' : 'background:#f0fdf4; border:1px solid #bbf7d0' }}">
                <div class="font-semibold mb-1 {{ $isFailure ? 'text-red-700' : 'text-green-700' }}">
                    {{ $isFailure ? 'Why did this fail?' : 'Status update' }}
                </div>
                <p class="text-ink-muted leading-relaxed">{{ $order->datasika_message }}</p>
            </div>
        @endif

        <div class="mt-6 space-y-2 border-t border-border pt-4 font-mono text-sm">
            <div class="flex justify-between"><span class="text-ink-faint">Order ID</span><span class="max-w-[60%] truncate">{{ $order->id }}</span></div>
            @if($order->data_sika_order_id)
            <div class="flex justify-between"><span class="text-ink-faint">Reference</span><span>{{ $order->data_sika_order_id }}</span></div>
            @endif
            <div class="flex justify-between"><span class="text-ink-faint">Bundle</span><span>{{ $order->product->network }} {{ intval($order->product->bundle_gb) }}GB</span></div>
            <div class="flex justify-between"><span class="text-ink-faint">Recipient</span><span>{{ $order->recipient }}</span></div>
            <div class="flex justify-between"><span class="text-ink-faint">Amount</span><span>GHS {{ number_format($order->amount_charged, 2) }}</span></div>
        </div>
    </div>

    @if($order->status === 'REFUNDED')
        <p class="rounded-lg bg-signal-tint px-4 py-2.5 text-sm text-signal">Your wallet has been credited back for this order.</p>
    @endif
</div>
@endsection