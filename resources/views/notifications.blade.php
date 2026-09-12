@extends('layouts.app')
@section('title', 'Notifications — Freedom Data')
@section('content')
<div class="mx-auto max-w-2xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="font-display text-2xl font-semibold">Notifications</h1>
        @if($notifications->where('is_read', false)->count() > 0)
            <form method="POST" action="/notifications/read-all">
                @csrf
                <button class="btn-dark text-xs">Mark all read</button>
            </form>
        @endif
    </div>

    @if($notifications->count())
        <div class="space-y-2">
            @foreach($notifications as $n)
                @php
                    $typeColors = [
                        'success' => 'border-l-signal',
                        'warning' => 'border-l-gold',
                        'alert' => 'border-l-alert',
                        'info' => 'border-l-ink-faint',
                    ];
                    $borderClass = $typeColors[$n->type] ?? 'border-l-ink-faint';
                @endphp

                {{-- Entire card is a form that marks as read on click --}}
                <div class="card p-4 border-l-4 {{ $borderClass }} {{ !$n->is_read ? 'bg-gold-tint/30' : '' }} {{ !$n->is_read ? 'cursor-pointer hover:shadow-md transition-shadow' : '' }}"
                     @if(!$n->is_read)
                     onclick="markRead({{ $n->id }}, this, '{{ $n->link }}')"
                     @elseif($n->link)
                     onclick="window.location='{{ $n->link }}'" style="cursor:pointer"
                     @endif>
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-display font-semibold text-sm">{{ $n->title }}</span>
                                @if(!$n->is_read)
                                    <span class="unread-dot h-2 w-2 rounded-full bg-gold inline-block"></span>
                                @endif
                            </div>
                            <p class="text-sm text-ink-muted mt-0.5">{{ $n->message }}</p>
                            <span class="text-xs text-ink-faint mt-1 block">{{ $n->created_at->diffForHumans() }}</span>
                        </div>
                        @if($n->link && $n->is_read)
                            <a href="{{ $n->link }}" class="text-xs text-gold-dark underline shrink-0" onclick="event.stopPropagation()">View</a>
                        @endif
                    </div>
                </div>

                {{-- Hidden mark-read form --}}
                @if(!$n->is_read)
                <form id="read-form-{{ $n->id }}" method="POST" action="/notifications/{{ $n->id }}/read" class="hidden">
                    @csrf
                </form>
                @endif
            @endforeach
        </div>

        @if($notifications->hasPages())
            <div class="mt-6 flex items-center justify-center gap-1">
                @if($notifications->onFirstPage())
                    <span class="px-3 py-2 text-sm text-ink-faint">← Previous</span>
                @else
                    <a href="{{ $notifications->previousPageUrl() }}" class="nav-pill text-sm">← Previous</a>
                @endif
                <span class="px-3 py-2 text-sm text-ink-muted">Page {{ $notifications->currentPage() }} of {{ $notifications->lastPage() }}</span>
                @if($notifications->hasMorePages())
                    <a href="{{ $notifications->nextPageUrl() }}" class="nav-pill text-sm">Next →</a>
                @else
                    <span class="px-3 py-2 text-sm text-ink-faint">Next →</span>
                @endif
            </div>
        @endif
    @else
        <div class="card p-8 text-center">
            <svg class="mx-auto mb-3" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-ink-faint)"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <p class="font-display font-semibold">No notifications yet</p>
            <p class="mt-1 text-sm text-ink-muted">You will be notified when orders are delivered, payouts processed, and more.</p>
        </div>
    @endif
</div>

@push('scripts')
<script>
function markRead(id, card, link) {
    // Submit the hidden form to mark as read
    const form = document.getElementById('read-form-' + id);
    if (!form) return;

    fetch(form.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({}),
    }).then(() => {
        // Remove unread styling
        card.classList.remove('bg-gold-tint/30');
        card.removeAttribute('onclick');
        const dot = card.querySelector('.unread-dot');
        if (dot) dot.remove();

        // Update the bell count in the navbar
        const bell = document.querySelector('nav .nav-pill .absolute');
        if (bell) {
            const count = parseInt(bell.textContent) - 1;
            if (count <= 0) {
                bell.remove();
            } else {
                bell.textContent = count > 9 ? '9+' : count;
            }
        }

        // Navigate to link if present
        if (link && link !== 'null' && link !== '') {
            window.location.href = link;
        }
    });
}
</script>
@endpush
@endsection