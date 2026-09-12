'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';

interface AdminProduct {
  id: string;
  network: string;
  bundleGb: string;
  costPrice: string;
  sellPrice: string;
  agentPrice: string | null;
  isAvailable: boolean;
}

export default function PricingEditor() {
  const [products, setProducts] = useState<AdminProduct[]>([]);
  const [drafts, setDrafts] = useState<Record<string, { sellPrice: string; agentPrice: string }>>({});
  const [savingId, setSavingId] = useState<string | null>(null);
  const [savedId, setSavedId] = useState<string | null>(null);

  function load() {
    api.get<AdminProduct[]>('/products/admin').then((res) => {
      setProducts(res.data);
      const next: Record<string, { sellPrice: string; agentPrice: string }> = {};
      for (const p of res.data) {
        next[p.id] = { sellPrice: p.sellPrice, agentPrice: p.agentPrice ?? '' };
      }
      setDrafts(next);
    });
  }

  useEffect(load, []);

  function setDraft(id: string, field: 'sellPrice' | 'agentPrice', value: string) {
    setDrafts((d) => ({ ...d, [id]: { ...d[id], [field]: value } }));
  }

  function margin(p: AdminProduct) {
    const sell = Number(drafts[p.id]?.sellPrice ?? p.sellPrice);
    const cost = Number(p.costPrice);
    if (!sell || !cost) return null;
    return (((sell - cost) / cost) * 100).toFixed(0);
  }

  async function save(p: AdminProduct) {
    setSavingId(p.id);
    setSavedId(null);
    try {
      const draft = drafts[p.id];
      await api.patch(`/products/${p.id}/pricing`, {
        sellPrice: Number(draft.sellPrice),
        agentPrice: draft.agentPrice ? Number(draft.agentPrice) : undefined,
      });
      setSavedId(p.id);
      setTimeout(() => setSavedId((cur) => (cur === p.id ? null : cur)), 1800);
    } finally {
      setSavingId(null);
    }
  }

  return (
    <div className="overflow-x-auto rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)]">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-[var(--color-border)] text-left text-[var(--color-ink-faint)]">
            <th className="p-3 font-medium">Bundle</th>
            <th className="p-3 font-medium">Cost (DataSika)</th>
            <th className="p-3 font-medium">Sell price</th>
            <th className="p-3 font-medium">Agent price</th>
            <th className="p-3 font-medium">Margin</th>
            <th className="p-3 font-medium">Status</th>
            <th className="p-3" />
          </tr>
        </thead>
        <tbody>
          {products.map((p) => (
            <tr key={p.id} className="border-b border-[var(--color-border)] last:border-0">
              <td className="p-3 font-display font-medium">
                {p.network} {p.bundleGb}GB
              </td>
              <td className="p-3 font-mono text-[var(--color-ink-faint)]">GHS {p.costPrice}</td>
              <td className="p-3">
                <span className="flex items-center gap-1 font-mono">
                  <span className="text-[var(--color-ink-faint)]">GHS</span>
                  <input
                    value={drafts[p.id]?.sellPrice ?? ''}
                    onChange={(e) => setDraft(p.id, 'sellPrice', e.target.value)}
                    className="w-20 rounded border border-[var(--color-border-strong)] bg-white px-2 py-1 outline-none focus:border-[var(--color-ink)]"
                  />
                </span>
              </td>
              <td className="p-3">
                <span className="flex items-center gap-1 font-mono">
                  <span className="text-[var(--color-ink-faint)]">GHS</span>
                  <input
                    value={drafts[p.id]?.agentPrice ?? ''}
                    onChange={(e) => setDraft(p.id, 'agentPrice', e.target.value)}
                    placeholder="—"
                    className="w-20 rounded border border-[var(--color-border-strong)] bg-white px-2 py-1 outline-none focus:border-[var(--color-ink)]"
                  />
                </span>
              </td>
              <td className="p-3 font-mono text-[var(--color-signal)]">
                {margin(p) !== null ? `+${margin(p)}%` : '—'}
              </td>
              <td className="p-3">
                <span
                  className={`rounded-full px-2 py-0.5 text-xs ${
                    p.isAvailable
                      ? 'bg-[var(--color-signal-tint)] text-[var(--color-signal)]'
                      : 'bg-[var(--color-alert-tint)] text-[var(--color-alert)]'
                  }`}
                >
                  {p.isAvailable ? 'Live' : 'Disabled'}
                </span>
              </td>
              <td className="p-3 text-right">
                <button
                  onClick={() => save(p)}
                  disabled={savingId === p.id}
                  className="rounded-lg bg-[var(--color-ink)] px-3 py-1.5 text-xs font-medium text-white hover:opacity-90 disabled:opacity-50"
                >
                  {savingId === p.id ? 'Saving…' : savedId === p.id ? 'Saved ✓' : 'Save'}
                </button>
              </td>
            </tr>
          ))}
          {products.length === 0 && (
            <tr>
              <td colSpan={7} className="p-6 text-center text-[var(--color-ink-faint)]">
                No products yet — run a catalog sync first.
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
