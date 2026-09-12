'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api, Product } from '@/lib/api';

const NETWORK_COLOR: Record<string, string> = {
  MTN: 'var(--color-mtn)',
  Telecel: 'var(--color-telecel)',
  AirtelTigo: 'var(--color-airteltigo)',
};

export default function StorefrontPage() {
  const router = useRouter();
  const [products, setProducts] = useState<Product[]>([]);
  const [selected, setSelected] = useState<Product | null>(null);
  const [recipient, setRecipient] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api.get<Product[]>('/products').then((res) => setProducts(res.data));
  }, []);

  const grouped = products.reduce<Record<string, Product[]>>((acc, p) => {
    (acc[p.network] ??= []).push(p);
    return acc;
  }, {});

  async function handleBuy(e: React.FormEvent) {
    e.preventDefault();
    if (!selected) return;
    setError(null);

    if (!/^0\d{9}$/.test(recipient)) {
      setError('Enter a valid 10-digit number starting with 0.');
      return;
    }

    setLoading(true);
    try {
      const res = await api.post('/orders', { productId: selected.id, recipient });
      router.push(`/track/${res.data.id}`);
    } catch (err: any) {
      const code = err?.response?.data?.message?.code ?? err?.response?.data?.code;
      if (code === 'insufficient_balance') {
        setError('Your wallet balance is too low for this bundle.');
      } else if (err?.response?.status === 401) {
        setError('Log in to buy a bundle.');
      } else {
        setError('Could not place the order. Try again in a moment.');
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="space-y-12">
      {/* Hero */}
      <section className="flex flex-col items-start gap-6 sm:flex-row sm:items-center sm:justify-between">
        <div className="max-w-xl">
          <span className="signal-bars hero-bars" aria-hidden>
            <span className="bar lit" />
            <span className="bar lit" />
            <span className="bar lit" />
            <span className="bar lit" />
          </span>
          <h1 className="font-display mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">
            Data, sent in minutes.
          </h1>
          <p className="mt-3 text-[var(--color-ink-muted)]">
            Buy MTN, Telecel and AirtelTigo bundles from your wallet, and watch each
            order move from received to delivered in real time — no refreshing.
          </p>
        </div>
        <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] px-5 py-4 text-sm">
          <div className="font-mono text-[var(--color-ink-faint)]">Prices in GHS · updated every 10 min</div>
        </div>
      </section>

      {/* Bundles + checkout */}
      <section className="grid gap-8 lg:grid-cols-3">
        <div className="space-y-8 lg:col-span-2">
          {Object.entries(grouped).map(([network, items]) => (
            <div key={network}>
              <div className="mb-3 flex items-center gap-2">
                <span
                  className="h-2.5 w-2.5 rounded-full"
                  style={{ background: NETWORK_COLOR[network] ?? 'var(--color-ink-faint)' }}
                  aria-hidden
                />
                <h2 className="font-display text-sm font-semibold uppercase tracking-wide text-[var(--color-ink-muted)]">
                  {network}
                </h2>
              </div>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {items.map((p) => {
                  const active = selected?.id === p.id;
                  return (
                    <button
                      key={p.id}
                      onClick={() => setSelected(p)}
                      className={`group relative overflow-hidden rounded-xl border p-4 text-left transition ${
                        active
                          ? 'border-[var(--color-ink)] bg-[var(--color-surface)] shadow-[0_1px_0_var(--color-ink)]'
                          : 'border-[var(--color-border)] bg-[var(--color-surface)] hover:border-[var(--color-border-strong)]'
                      }`}
                    >
                      <span
                        className="absolute left-0 top-0 h-full w-1"
                        style={{ background: NETWORK_COLOR[network] ?? 'transparent' }}
                        aria-hidden
                      />
                      <div className="font-display text-xl font-semibold">{p.bundleGb}GB</div>
                      <div className="mt-0.5 font-mono text-sm text-[var(--color-gold-dark)]">
                        GHS {p.price}
                      </div>
                      {p.serviceType === 'MTN_EXPRESS' && (
                        <span className="mt-2 inline-block rounded-full bg-[var(--color-gold-tint)] px-2 py-0.5 text-[11px] font-medium text-[var(--color-gold-dark)]">
                          Express
                        </span>
                      )}
                    </button>
                  );
                })}
              </div>
            </div>
          ))}
          {products.length === 0 && (
            <div className="rounded-xl border border-dashed border-[var(--color-border)] p-8 text-center text-sm text-[var(--color-ink-faint)]">
              Loading bundles…
            </div>
          )}
        </div>

        {/* Checkout panel */}
        <form
          onSubmit={handleBuy}
          className="h-fit space-y-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5 lg:sticky lg:top-6"
        >
          <h2 className="font-display font-semibold">Complete purchase</h2>

          {selected ? (
            <div className="flex items-center justify-between rounded-xl bg-[var(--color-paper-dim)] px-4 py-3">
              <div>
                <div className="font-display font-semibold">
                  {selected.network} {selected.bundleGb}GB
                </div>
                <div className="font-mono text-sm text-[var(--color-gold-dark)]">GHS {selected.price}</div>
              </div>
              <span
                className="h-3 w-3 shrink-0 rounded-full"
                style={{ background: NETWORK_COLOR[selected.network] ?? 'var(--color-ink-faint)' }}
                aria-hidden
              />
            </div>
          ) : (
            <p className="rounded-xl border border-dashed border-[var(--color-border)] px-4 py-3 text-sm text-[var(--color-ink-faint)]">
              Pick a bundle to continue.
            </p>
          )}

          <div>
            <label htmlFor="recipient" className="mb-1 block text-sm font-medium">
              Recipient number
            </label>
            <input
              id="recipient"
              type="tel"
              inputMode="numeric"
              placeholder="0241234567"
              value={recipient}
              onChange={(e) => setRecipient(e.target.value)}
              className="w-full rounded-lg border border-[var(--color-border-strong)] bg-white px-3 py-2.5 font-mono outline-none focus:border-[var(--color-ink)]"
            />
          </div>

          {error && (
            <p className="rounded-lg bg-[var(--color-alert-tint)] px-3 py-2 text-sm text-[var(--color-alert)]">
              {error}
            </p>
          )}

          <button
            type="submit"
            disabled={!selected || loading}
            className="w-full rounded-lg bg-[var(--color-gold)] py-2.5 font-display font-semibold text-white transition hover:bg-[var(--color-gold-dark)] disabled:cursor-not-allowed disabled:opacity-40"
          >
            {loading ? 'Placing order…' : 'Buy now'}
          </button>
        </form>
      </section>
    </div>
  );
}
