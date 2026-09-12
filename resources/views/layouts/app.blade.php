<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Freedom Data')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper text-ink">

    {{-- NAV --}}
    <header class="border-b border-border bg-surface">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="/" class="flex items-center gap-2.5">
                <span class="signal-bars">
                    <span class="bar lit"></span><span class="bar lit"></span><span class="bar lit"></span><span class="bar"></span>
                </span>
                <span class="font-display text-lg font-semibold tracking-tight">
                    Freedom <span class="text-gold">Data</span>
                </span>
            </a>

            {{-- Desktop nav --}}
            <nav class="desktop-nav items-center gap-1 text-sm">
                <a href="/" class="nav-pill {{ request()->is('/') ? 'active' : '' }}">Buy Data</a>
                <a href="/crypto" class="nav-pill {{ request()->is('crypto*') ? 'active' : '' }}">USDT</a>
                <a href="/track" class="nav-pill {{ request()->is('track*') ? 'active' : '' }}">Track</a>
                @auth
                    @php $unreadCount = auth()->user()->unreadNotificationCount(); @endphp
                    <a href="/notifications" class="nav-pill relative" title="Notifications">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        @if($unreadCount > 0)
                            <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-alert text-[10px] font-bold text-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                        @endif
                    </a>
                    <a href="/wallet" class="nav-pill {{ request()->is('wallet*') ? 'active' : '' }}">
                        Wallet <span class="ml-1 font-mono text-xs text-gold-dark">₵{{ number_format(auth()->user()->wallet_balance, 2) }}</span>
                    </a>
                    @if(auth()->user()->isActiveAgent())
                        <a href="/payout" class="nav-pill {{ request()->is('payout*') ? 'active' : '' }}">Payout</a>
                        <a href="/agent" class="nav-pill {{ request()->is('agent*') ? 'active' : '' }}">Agent</a>
                    @elseif(auth()->user()->role === 'CUSTOMER')
                        <a href="/agent/subscribe" class="nav-pill {{ request()->is('agent*') ? 'active' : '' }}">Become Agent</a>
                    @endif
                    @if(auth()->user()->isAdmin())
                        <a href="/admin" class="nav-pill {{ request()->is('admin*') ? 'active' : '' }}">Admin</a>
                    @endif
                    <form method="POST" action="/logout" class="inline">
                        @csrf
                        <button type="submit" class="nav-pill">Log out</button>
                    </form>
                @else
                    <a href="/login" class="ml-1 rounded-full bg-gold px-4 py-1.5 font-medium text-white hover:bg-gold-dark transition">Log in</a>
                @endauth
            </nav>

            {{-- Mobile hamburger --}}
            <button onclick="document.getElementById('mobile-nav').classList.toggle('hidden')" class="mobile-menu-btn p-2 rounded-lg border border-border" aria-label="Menu">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </div>

        {{-- Mobile dropdown --}}
        <div id="mobile-nav" class="mobile-dropdown hidden border-t border-border bg-surface px-4 pb-4">
            <div class="flex flex-col gap-1 pt-2 text-sm">
                <a href="/" class="nav-pill {{ request()->is('/') ? 'active' : '' }}">Buy Data</a>
                <a href="/crypto" class="nav-pill {{ request()->is('crypto*') ? 'active' : '' }}">USDT</a>
                <a href="/track" class="nav-pill {{ request()->is('track*') ? 'active' : '' }}">Track Order</a>
                @auth
                    <a href="/notifications" class="nav-pill flex items-center justify-between">
                        <span>Notifications</span>
                        @if(($unreadCount ?? auth()->user()->unreadNotificationCount()) > 0)
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-alert text-[10px] font-bold text-white">{{ ($unreadCount ?? auth()->user()->unreadNotificationCount()) > 9 ? '9+' : ($unreadCount ?? auth()->user()->unreadNotificationCount()) }}</span>
                        @endif
                    </a>
                    <a href="/wallet" class="nav-pill {{ request()->is('wallet*') ? 'active' : '' }}">
                        Wallet <span class="ml-1 font-mono text-xs text-gold-dark">₵{{ number_format(auth()->user()->wallet_balance, 2) }}</span>
                    </a>
                    @if(auth()->user()->isActiveAgent())
                        <a href="/payout" class="nav-pill {{ request()->is('payout*') ? 'active' : '' }}">Payout</a>
                        <a href="/agent" class="nav-pill {{ request()->is('agent*') ? 'active' : '' }}">Agent Dashboard</a>
                    @elseif(auth()->user()->role === 'CUSTOMER')
                        <a href="/agent/subscribe" class="nav-pill {{ request()->is('agent*') ? 'active' : '' }}">Become Agent</a>
                    @endif
                    @if(auth()->user()->isAdmin())
                        <a href="/admin" class="nav-pill {{ request()->is('admin*') ? 'active' : '' }}">Admin</a>
                    @endif
                    <form method="POST" action="/logout">
                        @csrf
                        <button type="submit" class="nav-pill w-full text-left text-alert">Log out</button>
                    </form>
                @else
                    <a href="/login" class="nav-pill">Log in</a>
                    <a href="/register" class="nav-pill">Sign up</a>
                @endauth
            </div>
        </div>
    </header>

    {{-- FLASH MESSAGES --}}
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        @if(session('success'))
            <div class="mt-4 rounded-xl bg-signal-tint px-4 py-3 text-sm text-signal">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mt-4 rounded-xl bg-alert-tint px-4 py-3 text-sm text-alert">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mt-4 rounded-xl bg-alert-tint px-4 py-3 text-sm text-alert">
                @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
            </div>
        @endif
    </div>

    {{-- CONTENT --}}
    <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        @yield('content')
    </main>

    {{-- FOOTER --}}
    <footer class="mt-16 border-t border-border">
        <div class="mx-auto max-w-6xl px-4 py-6 text-xs text-ink-faint sm:px-6">
            Freedom Data · Reseller Platform
        </div>
    </footer>

    @stack('scripts')

    <script>
    // Global loading overlay — fires on any form submit site-wide
    // Skips forms with data-no-loading attribute
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form').forEach(form => {
            if (form.dataset.noLoading) return;
            form.addEventListener('submit', function (e) {
                // If the form was cancelled by a confirm() dialog, don't show overlay
                if (e.defaultPrevented) return;

                // Small delay so confirm() dialogs on onclick handlers have time to cancel
                setTimeout(() => {
                    if (!document.getElementById('_loading_overlay')) {
                        const overlay = document.createElement('div');
                        overlay.id = '_loading_overlay';
                        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;';
                        overlay.innerHTML = `
                            <div style="width:52px;height:52px;border:4px solid rgba(255,255,255,0.2);border-top-color:#fff;border-radius:50%;animation:_spin 0.8s linear infinite;"></div>
                            <div style="color:#fff;font-size:16px;font-weight:600;">Processing...</div>
                            <div style="color:rgba(255,255,255,0.6);font-size:13px;">Please wait, do not close this page.</div>
                            <style>@keyframes _spin{to{transform:rotate(360deg)}}</style>
                        `;
                        document.body.appendChild(overlay);
                    }
                }, 50);
            });
        });
    });
    </script>
</body>
</html>
