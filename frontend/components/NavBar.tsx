'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { disconnectSocket } from '@/lib/socket';

export default function NavBar() {
  const pathname = usePathname();
  const router = useRouter();
  const [loggedIn, setLoggedIn] = useState(false);
  const [role, setRole] = useState<string | null>(null);
  const [balance, setBalance] = useState<string | null>(null);
  const [menuOpen, setMenuOpen] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem('token');
    const savedRole = localStorage.getItem('role');
    setLoggedIn(Boolean(token));
    setRole(savedRole);
    setMenuOpen(false);
    if (token) {
      api.get('/wallet/balance').then((res) => setBalance(String(res.data.balance))).catch(() => setBalance(null));
    } else {
      setBalance(null);
    }
  }, [pathname]);

  const links = [
    { href: '/', label: 'Buy Data' },
    { href: '/track', label: 'Track' },
    ...(role === 'AGENT' ? [{ href: '/agent', label: 'Agent' }] : []),
    ...(role === 'ADMIN' ? [{ href: '/admin', label: 'Admin' }] : []),
  ];

  function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('userId');
    localStorage.removeItem('role');
    disconnectSocket();
    setMenuOpen(false);
    router.push('/');
    router.refresh();
  }

  return (
    <header className="border-b border-[var(--color-border)] bg-[var(--color-surface)]">
      <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
        <Link href="/" className="flex items-center gap-2.5" aria-label="Freedom Data home">
          <span className="signal-bars" aria-hidden>
            <span className="bar lit" /><span className="bar lit" /><span className="bar lit" /><span className="bar" />
          </span>
          <span className="font-display text-lg font-semibold tracking-tight">Freedom <span className="text-[var(--color-gold)]">Data</span></span>
        </Link>

        <nav className="hidden items-center gap-1 text-sm md:flex" aria-label="Main navigation">
          {links.map((link) => <NavLink key={link.href} {...link} active={pathname === link.href} />)}
          {loggedIn && (
            <Link href="/wallet" className="rounded-full px-3 py-1.5 text-[var(--color-ink-muted)] hover:bg-[var(--color-paper-dim)]">
              Wallet {balance !== null && <span className="ml-1 font-mono text-xs text-[var(--color-gold-dark)]">₵{Number(balance).toFixed(2)}</span>}
            </Link>
          )}
          {loggedIn ? (
            <button onClick={logout} className="rounded-full px-3 py-1.5 text-[var(--color-ink-muted)] hover:bg-[var(--color-paper-dim)]">Log out</button>
          ) : (
            <Link href="/login" className="ml-1 rounded-full bg-[var(--color-gold)] px-4 py-1.5 font-medium text-white hover:bg-[var(--color-gold-dark)]">Log in</Link>
          )}
        </nav>

        <button type="button" onClick={() => setMenuOpen((open) => !open)} className="rounded-lg border border-[var(--color-border)] p-2 md:hidden" aria-expanded={menuOpen} aria-label="Toggle menu">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
            {menuOpen ? <><path d="M6 6l12 12" /><path d="M18 6L6 18" /></> : <><path d="M3 6h18" /><path d="M3 12h18" /><path d="M3 18h18" /></>}
          </svg>
        </button>
      </div>

      {menuOpen && (
        <nav className="border-t border-[var(--color-border)] bg-white px-4 pb-4 pt-2 text-sm md:hidden" aria-label="Mobile navigation">
          <div className="mx-auto flex max-w-6xl flex-col gap-1">
            {links.map((link) => <NavLink key={link.href} {...link} active={pathname === link.href} />)}
            {loggedIn && <div className="px-3 py-2 text-[var(--color-ink-muted)]">Wallet <span className="font-mono text-[var(--color-gold-dark)]">₵{balance === null ? '—' : Number(balance).toFixed(2)}</span></div>}
            {loggedIn ? <button onClick={logout} className="rounded-lg px-3 py-2 text-left text-[var(--color-alert)] hover:bg-[var(--color-alert-tint)]">Log out</button> : <NavLink href="/login" label="Log in" active={pathname === '/login'} />}
          </div>
        </nav>
      )}
    </header>
  );
}

function NavLink({ href, label, active }: { href: string; label: string; active: boolean }) {
  return <Link href={href} className={`rounded-full px-3 py-1.5 transition ${active ? 'bg-[var(--color-ink)] text-[var(--color-paper)]' : 'text-[var(--color-ink-muted)] hover:bg-[var(--color-paper-dim)]'}`}>{label}</Link>;
}
