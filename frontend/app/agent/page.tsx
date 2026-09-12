'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import NetworkLogo from '@/components/NetworkLogo';
import { api, Product } from '@/lib/api';

interface AgentOrder {
  id: string;
  status: string;
  amountCharged: string;
  recipient: string;
  createdAt: string;
  product: { network: string; bundleGb: string };
}

export default function AgentPage() {
  const router = useRouter();
  const [products, setProducts] = useState<Product[]>([]);
  const [orders, setOrders] = useState<AgentOrder[]>([]);
  const [balance, setBalance] = useState('0.00');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (localStorage.getItem('role') !== 'AGENT') {
      router.replace('/');
      return;
    }
    Promise.all([api.get<Product[]>('/products'), api.get('/orders/mine'), api.get('/wallet/balance')])
      .then(([productResponse, orderResponse, balanceResponse]) => {
        setProducts(productResponse.data);
        setOrders(orderResponse.data);
        setBalance(String(balanceResponse.data.balance));
      })
      .finally(() => setLoading(false));
  }, [router]);

  if (loading) return <div className="rounded-2xl border border-[var(--color-border)] bg-white p-8 text-center text-sm text-[var(--color-ink-faint)]">Loading agent store…</div>;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div><div className="mb-2 inline-flex rounded-full bg-[var(--color-signal-tint)] px-3 py-1 text-xs font-semibold text-[var(--color-signal)]">Agent store active</div><h1 className="font-display text-2xl font-semibold">Agent Dashboard</h1><p className="text-sm text-[var(--color-ink-muted)]">Your wholesale catalog, wallet, and recent purchases.</p></div>
        <Link href="/" className="rounded-lg bg-[var(--color-gold)] px-4 py-2.5 font-display text-sm font-semibold text-white hover:bg-[var(--color-gold-dark)]">Open agent store</Link>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <Stat label="Wallet balance" value={`₵${Number(balance).toFixed(2)}`} accent />
        <Stat label="Total orders" value={String(orders.length)} />
        <Stat label="Agent-priced bundles" value={String(products.filter((product) => product.hasAgentPrice).length)} />
      </div>

      <section className="rounded-2xl border border-[var(--color-border)] bg-white p-5">
        <div className="mb-4"><h2 className="font-display font-semibold">Your wholesale catalog</h2><p className="text-sm text-[var(--color-ink-muted)]">These are the prices charged to your agent wallet.</p></div>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {products.map((product) => <div key={product.id} className="flex items-center gap-3 rounded-xl border border-[var(--color-border)] p-3"><NetworkLogo network={product.network} size={38} /><div className="min-w-0 flex-1"><div className="font-display font-semibold">{product.network} {Number(product.bundleGb)}GB</div><div className="font-mono text-sm text-[var(--color-gold-dark)]">₵{Number(product.price).toFixed(2)}</div></div>{product.hasAgentPrice && <span className="rounded-full bg-[var(--color-signal-tint)] px-2 py-1 text-[10px] font-semibold text-[var(--color-signal)]">Agent</span>}</div>)}
        </div>
      </section>

      <section className="overflow-x-auto rounded-2xl border border-[var(--color-border)] bg-white">
        <div className="border-b border-[var(--color-border)] p-4 font-display font-semibold">Recent orders</div>
        <table className="w-full min-w-[650px] text-sm"><thead><tr className="border-b border-[var(--color-border)] text-left text-[var(--color-ink-faint)]"><th className="p-3 font-medium">Bundle</th><th className="p-3 font-medium">Recipient</th><th className="p-3 font-medium">Cost</th><th className="p-3 font-medium">Status</th><th className="p-3 font-medium">Date</th></tr></thead><tbody>{orders.slice(0, 20).map((order) => <tr key={order.id} className="border-b border-[var(--color-border)] last:border-0"><td className="p-3">{order.product.network} {Number(order.product.bundleGb)}GB</td><td className="p-3 font-mono">{order.recipient}</td><td className="p-3 font-mono">₵{Number(order.amountCharged).toFixed(2)}</td><td className="p-3"><span className="rounded-full bg-[var(--color-paper-dim)] px-2.5 py-1 text-xs font-medium">{order.status}</span></td><td className="p-3 text-[var(--color-ink-faint)]">{new Date(order.createdAt).toLocaleDateString()}</td></tr>)}{orders.length === 0 && <tr><td colSpan={5} className="p-8 text-center text-[var(--color-ink-faint)]">No agent orders yet.</td></tr>}</tbody></table>
      </section>
    </div>
  );
}

function Stat({ label, value, accent = false }: { label: string; value: string; accent?: boolean }) {
  return <div className="rounded-2xl border border-[var(--color-border)] bg-white p-4"><div className="text-xs text-[var(--color-ink-faint)]">{label}</div><div className={`font-display mt-1 text-2xl font-semibold ${accent ? 'text-[var(--color-signal)]' : ''}`}>{value}</div></div>;
}
