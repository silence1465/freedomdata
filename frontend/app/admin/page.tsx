'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { getSocket } from '@/lib/socket';
import PricingEditor from '@/components/PricingEditor';

interface AdminOrder {
  id: string;
  status: string;
  amountCharged: string;
  costAmount: string;
  recipient: string;
  createdAt: string;
  product: { network: string; bundleGb: string };
}

const STATUS_STYLE: Record<string, string> = {
  DELIVERED: 'bg-[var(--color-signal-tint)] text-[var(--color-signal)]',
  PENDING: 'bg-[var(--color-gold-tint)] text-[var(--color-gold-dark)]',
  PROCESSING: 'bg-[var(--color-gold-tint)] text-[var(--color-gold-dark)]',
  FAILED: 'bg-[var(--color-alert-tint)] text-[var(--color-alert)]',
  REFUNDED: 'bg-[var(--color-paper-dim)] text-[var(--color-ink-muted)]',
};

export default function AdminPage() {
  const router = useRouter();
  const [authChecked, setAuthChecked] = useState(false);
  const [tab, setTab] = useState<'orders' | 'pricing'>('orders');
  const [orders, setOrders] = useState<AdminOrder[]>([]);
  const [balance, setBalance] = useState<string | null>(null);
  const [syncing, setSyncing] = useState(false);
  const [syncMsg, setSyncMsg] = useState<string | null>(null);

  useEffect(() => {
    const role = localStorage.getItem('role');
    if (role !== 'ADMIN') {
      router.replace('/');
      return;
    }
    setAuthChecked(true);
  }, [router]);

  useEffect(() => {
    if (!authChecked) return;
    api.get('/orders/mine').then((res) => setOrders(res.data)).catch(() => {});
    api.get('/wallet/balance').then((res) => setBalance(res.data.balance)).catch(() => {});

    const socket = getSocket();
    socket.emit('subscribe:user');

    function onWalletUpdate(payload: { balance: string }) {
      setBalance(payload.balance);
    }
    socket.on('wallet:update', onWalletUpdate);
    return () => {
      socket.off('wallet:update', onWalletUpdate);
    };
  }, [authChecked]);

  async function triggerSync() {
    setSyncing(true);
    setSyncMsg(null);
    try {
      const res = await api.post('/products/sync');
      setSyncMsg(`Synced — ${res.data.created} new, ${res.data.updated} updated.`);
    } catch {
      setSyncMsg('Sync failed. This account needs the ADMIN role.');
    } finally {
      setSyncing(false);
    }
  }

  const margin = orders.reduce((sum, o) => sum + (Number(o.amountCharged) - Number(o.costAmount)), 0);

  if (!authChecked) {
    return (
      <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-8 text-center text-sm text-[var(--color-ink-faint)]">
        Checking access…
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-display text-2xl font-semibold">Control tower</h1>
        <p className="text-sm text-[var(--color-ink-muted)]">Wallet, pricing and the live order ledger.</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard label="Your wallet balance" value={balance ? `GHS ${balance}` : '—'} />
        <StatCard label="Total orders" value={String(orders.length)} />
        <StatCard label="Margin earned" value={`GHS ${margin.toFixed(2)}`} accent />
      </div>

      <div className="flex items-center justify-between rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
        <div>
          <h2 className="font-display font-semibold">Catalog sync</h2>
          <p className="text-sm text-[var(--color-ink-muted)]">
            Pulls prices and availability from DataSika. Runs automatically every 10 minutes.
          </p>
          {syncMsg && <p className="mt-1 font-mono text-xs text-[var(--color-ink-faint)]">{syncMsg}</p>}
        </div>
        <button
          onClick={triggerSync}
          disabled={syncing}
          className="shrink-0 rounded-lg bg-[var(--color-gold)] px-4 py-2 text-sm font-display font-semibold text-white hover:bg-[var(--color-gold-dark)] disabled:opacity-50"
        >
          {syncing ? 'Syncing…' : 'Sync now'}
        </button>
      </div>

      <div className="flex gap-1 border-b border-[var(--color-border)]">
        <TabButton active={tab === 'orders'} onClick={() => setTab('orders')}>
          Order ledger
        </TabButton>
        <TabButton active={tab === 'pricing'} onClick={() => setTab('pricing')}>
          Pricing
        </TabButton>
      </div>

      {tab === 'orders' ? (
        <div className="overflow-x-auto rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)]">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-[var(--color-border)] text-left text-[var(--color-ink-faint)]">
                <th className="p-3 font-medium">Order</th>
                <th className="p-3 font-medium">Bundle</th>
                <th className="p-3 font-medium">Recipient</th>
                <th className="p-3 font-medium">Charged</th>
                <th className="p-3 font-medium">Cost</th>
                <th className="p-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {orders.map((o) => (
                <tr key={o.id} className="border-b border-[var(--color-border)] last:border-0">
                  <td className="p-3 font-mono text-xs text-[var(--color-ink-faint)]">{o.id.slice(0, 8)}</td>
                  <td className="p-3">{o.product.network} {o.product.bundleGb}GB</td>
                  <td className="p-3 font-mono">{o.recipient}</td>
                  <td className="p-3 font-mono">GHS {o.amountCharged}</td>
                  <td className="p-3 font-mono text-[var(--color-ink-faint)]">GHS {o.costAmount}</td>
                  <td className="p-3">
                    <span className={`rounded-full px-2 py-0.5 text-xs ${STATUS_STYLE[o.status] ?? 'bg-[var(--color-paper-dim)] text-[var(--color-ink-muted)]'}`}>
                      {o.status}
                    </span>
                  </td>
                </tr>
              ))}
              {orders.length === 0 && (
                <tr>
                  <td colSpan={6} className="p-6 text-center text-[var(--color-ink-faint)]">
                    No orders yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      ) : (
        <PricingEditor />
      )}
    </div>
  );
}

function StatCard({ label, value, accent }: { label: string; value: string; accent?: boolean }) {
  return (
    <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
      <div className="text-xs text-[var(--color-ink-faint)]">{label}</div>
      <div className={`font-display mt-1 text-2xl font-semibold ${accent ? 'text-[var(--color-signal)]' : ''}`}>
        {value}
      </div>
    </div>
  );
}

function TabButton({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      onClick={onClick}
      className={`-mb-px border-b-2 px-3 py-2 text-sm font-medium transition ${
        active
          ? 'border-[var(--color-ink)] text-[var(--color-ink)]'
          : 'border-transparent text-[var(--color-ink-faint)] hover:text-[var(--color-ink-muted)]'
      }`}
    >
      {children}
    </button>
  );
}
