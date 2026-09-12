'use client';

import { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api, Product } from '@/lib/api';

const NETWORKS = ['MTN Flexa', 'MTN', 'Telecel', 'AirtelTigo'];
const NETWORK_COLOR: Record<string, string> = {
  'MTN Flexa': '#ffcc08',
  MTN: '#ffcc08',
  Telecel: '#e4032e',
  AirtelTigo: '#1b4f9c',
};
const NETWORK_BG: Record<string, string> = {
  'MTN Flexa': '#fff9d9',
  MTN: '#fffdf5',
  Telecel: '#fff7f7',
  AirtelTigo: '#f4f7ff',
};

export default function StorefrontPage() {
  const router = useRouter();
  const [products, setProducts] = useState<Product[]>([]);
  const [selected, setSelected] = useState<Product | null>(null);
  const [recipient, setRecipient] = useState('');
  const [balance, setBalance] = useState<string | null>(null);
  const [isAgent, setIsAgent] = useState(false);
  const [loggedIn, setLoggedIn] = useState(false);
  const [loading, setLoading] = useState(false);
  const [catalogLoading, setCatalogLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const token = localStorage.getItem('token');
    setLoggedIn(Boolean(token));
    setIsAgent(localStorage.getItem('role') === 'AGENT');

    const requests: Promise<unknown>[] = [
      api.get<Product[]>('/products').then((res) => setProducts(res.data)),
    ];
    if (token) {
      requests.push(api.get('/wallet/balance').then((res) => setBalance(String(res.data.balance))));
    }

    Promise.allSettled(requests).finally(() => setCatalogLoading(false));
  }, []);

  const grouped = useMemo(() => {
    const groups = products.reduce<Record<string, Product[]>>((acc, product) => {
      (acc[product.network] ??= []).push(product);
      return acc;
    }, {});
    return Object.entries(groups).sort(([a], [b]) => {
      const ai = NETWORKS.indexOf(a);
      const bi = NETWORKS.indexOf(b);
      return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
    });
  }, [products]);

  async function handleBuy(e: React.FormEvent) {
    e.preventDefault();
    if (!selected) return;
    setError(null);

    if (!localStorage.getItem('token')) {
      setError('Log in to pay securely from your wallet.');
      return;
    }
    if (!/^0\d{9}$/.test(recipient)) {
      setError('Enter a valid 10-digit number starting with 0.');
      return;
    }

    setLoading(true);
    try {
      const res = await api.post('/orders', { productId: selected.id, recipient });
      router.push(`/track/${res.data.id}`);
    } catch (err: any) {
      const code = err?.response?.data?.message?.code ?? err?.response?.data?.message;
      if (code === 'insufficient_balance') {
        setError('Your wallet balance is too low for this bundle.');
      } else if (err?.response?.status === 401) {
        setError('Your session has expired. Log in again to continue.');
      } else {
        setError('Could not place the order. Try again in a moment.');
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="space-y-10">
      <section className="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
        <div className="max-w-xl">
          <span className="signal-bars hero-bars" aria-hidden>
            <span className="bar lit" /><span className="bar lit" />
            <span className="bar lit" /><span className="bar lit" />
          </span>
          <div className="mt-4 flex flex-wrap items-center gap-3">
            <h1 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">Data, sent in minutes.</h1>
            {isAgent && (
              <span className="rounded-full bg-[var(--color-signal-tint)] px-3 py-1 text-xs font-semibold text-[var(--color-signal)]">
                Agent store
              </span>
            )}
          </div>
          <p className="mt-3 text-[var(--color-ink-muted)]">
            Buy MTN, Telecel and AirtelTigo bundles from your wallet. Track every order live.
          </p>
        </div>
        <div className="hidden rounded-2xl border border-[var(--color-border)] bg-white px-5 py-4 font-mono text-sm text-[var(--color-ink-faint)] sm:block">
          Prices in GHS · updated automatically
        </div>
      </section>

      <section className="grid gap-8 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          {grouped.map(([network, items]) => {
            const color = NETWORK_COLOR[network] ?? 'var(--color-gold)';
            const background = NETWORK_BG[network] ?? 'var(--color-paper-dim)';
            return (
              <section key={network} className="overflow-hidden rounded-2xl border" style={{ borderColor: `${color}55`, background }}>
                <header className="flex items-center gap-3 px-4 py-4 sm:px-5">
                  <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-white font-display text-xs font-bold shadow-sm" style={{ color }}>
                    {network === 'AirtelTigo' ? 'AT' : network === 'Telecel' ? 'T' : 'MTN'}
                  </span>
                  <div>
                    <h2 className="font-display text-lg font-semibold">{network}</h2>
                    <p className="text-xs text-[var(--color-ink-muted)]">{items.length} bundles available</p>
                  </div>
                  {isAgent && (
                    <span className="ml-auto rounded-full bg-[var(--color-signal-tint)] px-2.5 py-1 text-[11px] font-semibold text-[var(--color-signal)]">
                      Agent pricing
                    </span>
                  )}
                </header>

                <div className="grid grid-cols-2 gap-2.5 p-3 pt-0 sm:grid-cols-3 sm:gap-3 sm:p-4 sm:pt-0 lg:grid-cols-4">
                  {items.map((product) => {
                    const active = selected?.id === product.id;
                    return (
                      <button
                        key={product.id}
                        type="button"
                        onClick={() => { setSelected(product); setError(null); }}
                        className={`rounded-xl border bg-white p-3 text-left shadow-sm transition sm:p-4 ${active ? 'border-[var(--color-ink)] ring-2 ring-[var(--color-ink)]/10' : 'border-[var(--color-border)] hover:-translate-y-0.5 hover:shadow-md'}`}
                      >
                        <div className="font-display text-2xl font-bold">
                          {formatBundle(product.bundleGb)}<span className="ml-0.5 text-sm font-semibold text-[var(--color-ink-muted)]">GB</span>
                        </div>
                        <div className="mt-1 font-mono text-base font-semibold" style={{ color }}>₵{money(product.price)}</div>
                        {product.hasAgentPrice && (
                          <>
                            <div className="mt-0.5 text-[11px] text-[var(--color-ink-faint)] line-through">₵{money(product.retailPrice)}</div>
                            <span className="mt-1 inline-block rounded-full bg-[var(--color-signal-tint)] px-2 py-0.5 text-[10px] font-semibold text-[var(--color-signal)]">Agent price</span>
                          </>
                        )}
                        {product.serviceType === 'MTN_EXPRESS' && (
                          <span className="mt-1 inline-block rounded-full bg-[var(--color-gold-tint)] px-2 py-0.5 text-[10px] font-semibold text-[var(--color-gold-dark)]">Express</span>
                        )}
                      </button>
                    );
                  })}
                </div>
              </section>
            );
          })}

          {catalogLoading && <StoreMessage>Loading available bundles…</StoreMessage>}
          {!catalogLoading && products.length === 0 && <StoreMessage>No bundles are currently available. Please check back shortly.</StoreMessage>}
        </div>

        <form onSubmit={handleBuy} className="h-fit space-y-4 rounded-2xl border border-[var(--color-border)] bg-white p-5 shadow-sm lg:sticky lg:top-6">
          <div className="flex items-center justify-between">
            <h2 className="font-display text-lg font-semibold">Complete purchase</h2>
            {isAgent && <span className="text-xs font-semibold text-[var(--color-signal)]">Agent wallet</span>}
          </div>

          {selected ? (
            <div className="flex items-center justify-between rounded-xl border border-[var(--color-border)] bg-[var(--color-paper-dim)] px-4 py-3">
              <div>
                <div className="font-display font-semibold">{selected.network} {formatBundle(selected.bundleGb)}GB</div>
                <div className="font-mono text-sm font-semibold text-[var(--color-gold-dark)]">₵{money(selected.price)}</div>
              </div>
              <span className="h-3 w-3 rounded-full" style={{ background: NETWORK_COLOR[selected.network] ?? 'var(--color-gold)' }} />
            </div>
          ) : (
            <div className="rounded-xl border-2 border-dashed border-[var(--color-border-strong)] bg-[var(--color-paper-dim)] px-4 py-4 text-center text-sm text-[var(--color-ink-faint)]">
              Tap a bundle to select it.
            </div>
          )}

          <div>
            <label htmlFor="recipient" className="mb-1 block text-sm font-medium">Recipient number</label>
            <input id="recipient" type="tel" inputMode="numeric" maxLength={10} placeholder="0241234567" value={recipient} onChange={(e) => setRecipient(e.target.value.replace(/\D/g, ''))} className="w-full rounded-lg border border-[var(--color-border-strong)] bg-white px-3 py-2.5 font-mono outline-none focus:border-[var(--color-ink)]" />
          </div>

          {error && <p className="rounded-lg bg-[var(--color-alert-tint)] px-3 py-2 text-sm text-[var(--color-alert)]">{error}</p>}

          <button type="submit" disabled={!selected || loading} className="w-full rounded-lg bg-[var(--color-gold)] py-3 font-display font-semibold text-white transition hover:bg-[var(--color-gold-dark)] disabled:cursor-not-allowed disabled:opacity-40">
            {loading ? 'Placing order…' : balance !== null ? `Pay from wallet (₵${money(balance)})` : 'Pay from wallet'}
          </button>

          {isAgent ? (
            <p className="text-center text-xs text-[var(--color-ink-faint)]">Agent accounts receive configured wholesale prices and use wallet checkout.</p>
          ) : !loggedIn ? (
            <p className="text-center text-xs text-[var(--color-ink-faint)]">Log in to purchase and access your wallet.</p>
          ) : null}
        </form>
      </section>
    </div>
  );
}

function formatBundle(value: string) {
  const amount = Number(value);
  return Number.isInteger(amount) ? String(amount) : amount.toString();
}

function money(value: string) {
  return Number(value).toFixed(2);
}

function StoreMessage({ children }: { children: React.ReactNode }) {
  return <div className="rounded-2xl border border-dashed border-[var(--color-border)] bg-white p-8 text-center text-sm text-[var(--color-ink-faint)]">{children}</div>;
}
