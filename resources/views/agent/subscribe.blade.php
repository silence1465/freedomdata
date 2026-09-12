@extends('layouts.app')
@section('title', 'Become an Agent — Freedom Data')
@section('content')
<div class="mx-auto max-w-lg space-y-6">
    <div class="text-center">
        <span class="signal-bars" style="justify-content:center">
            <span class="bar lit"></span><span class="bar lit"></span><span class="bar lit"></span><span class="bar lit"></span>
        </span>
        <h1 class="font-display mt-4 text-2xl font-semibold">Become an Agent</h1>
        <p class="mt-2 text-ink-muted">Get discounted wholesale pricing on all bundles. Buy cheaper, resell to your own customers, keep the margin.</p>
    </div>

    @if(!$plan->is_enabled)
        <div class="card p-8 text-center">
            <p class="font-display font-semibold">Agent subscriptions are currently closed</p>
            <p class="mt-1 text-sm text-ink-muted">Check back later.</p>
        </div>
    @else
        @if($user->isActiveAgent())
            <div class="card p-5 bg-signal-tint border-signal">
                <div class="font-display font-semibold text-signal">You are an active agent</div>
                <div class="mt-1 text-sm">Your subscription expires on <strong>{{ $user->agent_expires_at->format('M d, Y') }}</strong>. You can renew below to extend it.</div>
            </div>
        @elseif($user->role === 'AGENT' && $user->agent_expires_at && $user->agent_expires_at->isPast())
            <div class="card p-5 bg-alert-tint border-alert">
                <div class="font-display font-semibold text-alert">Your agent subscription expired</div>
                <div class="mt-1 text-sm">Renew below to regain agent pricing.</div>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            {{-- Monthly --}}
            <form method="POST" action="/agent/subscribe" class="card p-6 text-center space-y-4">
                @csrf
                <input type="hidden" name="period" value="monthly">
                <div class="font-display text-lg font-semibold">Monthly</div>
                <div class="font-display text-3xl font-bold text-gold">₵{{ number_format($plan->monthly_fee, 2) }}</div>
                <div class="text-sm text-ink-muted">per month</div>
                <ul class="text-sm text-left space-y-1 text-ink-muted">
                    <li>✓ Wholesale bundle prices</li>
                    <li>✓ Set your own resell prices</li>
                    <li>✓ Agent dashboard</li>
                    <li>✓ Renew or cancel anytime</li>
                </ul>
                <button class="btn-gold w-full">Subscribe — ₵{{ number_format($plan->monthly_fee, 2) }}</button>
                <p class="text-xs text-ink-faint">Charged from your wallet balance</p>
            </form>

            {{-- Yearly --}}
            <form method="POST" action="/agent/subscribe" class="card p-6 text-center space-y-4 border-2 border-gold">
                @csrf
                <input type="hidden" name="period" value="yearly">
                <div class="rounded-full bg-gold-tint px-3 py-1 text-xs font-semibold text-gold-dark inline-block">Best value</div>
                <div class="font-display text-lg font-semibold">Yearly</div>
                <div class="font-display text-3xl font-bold text-gold">₵{{ number_format($plan->yearly_fee, 2) }}</div>
                <div class="text-sm text-ink-muted">per year</div>
                @php $savedPercent = $plan->monthly_fee > 0 ? round((1 - $plan->yearly_fee / ($plan->monthly_fee * 12)) * 100) : 0; @endphp
                <ul class="text-sm text-left space-y-1 text-ink-muted">
                    <li>✓ Everything in monthly</li>
                    <li>✓ Save {{ $savedPercent }}% vs monthly</li>
                    <li>✓ Lock in pricing for 12 months</li>
                </ul>
                <button class="btn-gold w-full">Subscribe — ₵{{ number_format($plan->yearly_fee, 2) }}</button>
                <p class="text-xs text-ink-faint">Charged from your wallet balance</p>
            </form>
        </div>
    @endif
</div>
@endsection
