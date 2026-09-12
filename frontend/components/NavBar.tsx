'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { disconnectSocket } from '@/lib/socket';

const BASE_LINKS = [
  { href: '/', label: 'Buy data' },
  { href: '/track', label: 'Track order' },
];

export default function NavBar() {
  const pathname = usePathname();
  const router = useRouter();
  const [loggedIn, setLoggedIn] = useState(false);
  const [isAdmin, setIsAdmin] = useState(false);

  useEffect(() => {
    setLoggedIn(!!localStorage.getItem('token'));
    setIsAdmin(localStorage.getItem('role') === 'ADMIN');
  }, [pathname]);

  const links = isAdmin ? [...BASE_LINKS, { href: '/admin', label: 'Admin' }] : BASE_LINKS;

  function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('userId');
    localStorage.removeItem('role');
    disconnectSocket();
    router.push('/');
    router.refresh();
  }

  return (
    <header className="border-b border-[var(--color-border)] bg-[var(--color-surface)]">
      <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 sm:px-6">
        <Link href="/" className="flex items-center gap-2.5">
          <span className="signal-bars" aria-hidden>
            <span className="bar lit" />
            <span className="bar lit" />
            <span className="bar lit" />
            <span className="bar" />
          </span>
          <span className="font-display text-lg font-semibold tracking-tight">
            Freedom <span className="text-[var(--color-gold)]">Data</span>
          </span>
        </Link>

        <nav className="flex items-center gap-1 text-sm">
          {links.map((l) => {
            const active = pathname === l.href;
            return (
              <Link
                key={l.href}
                href={l.href}
                className={`rounded-full px-3 py-1.5 transition ${
                  active
                    ? 'bg-[var(--color-ink)] text-[var(--color-paper)]'
                    : 'text-[var(--color-ink-muted)] hover:bg-[var(--color-paper-dim)]'
                }`}
              >
                {l.label}
              </Link>
            );
          })}
          {loggedIn ? (
            <button
              onClick={logout}
              className="ml-1 rounded-full px-3 py-1.5 text-sm text-[var(--color-ink-muted)] hover:bg-[var(--color-paper-dim)]"
            >
              Log out
            </button>
          ) : (
            <Link
              href="/login"
              className="ml-1 rounded-full bg-[var(--color-gold)] px-3.5 py-1.5 font-medium text-white hover:bg-[var(--color-gold-dark)]"
            >
              Log in
            </Link>
          )}
        </nav>
      </div>
    </header>
  );
}
