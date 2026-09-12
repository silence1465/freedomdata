'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import NetworkLogo from '@/components/NetworkLogo';
import { api, Product } from '@/lib/api';
import AgentNotifications from '@/components/AgentNotifications';

type ReviewStatus = 'SUBMITTED' | 'HOLD' | 'APPROVED' | 'REJECTED';
interface StoreRequest {
  id: string;
  recipient: string;
  customerName?: string;
  customerPhone?: string;
  transactionId?: string;
  status: ReviewStatus;
  reviewNote?: string;
  hasProof: boolean;
  createdAt: string;
  product: { network: string; bundleGb: string; sellPrice: string; agentPrice?: string };
  order?: { id: string; status: string };
}
interface AgentOrder { id: string; status: string; amountCharged: string; recipient: string; createdAt: string; product: { network: string; bundleGb: string } }

export default function AgentPage() {
  const router = useRouter();
  const [products, setProducts] = useState<Product[]>([]);
  const [orders, setOrders] = useState<AgentOrder[]>([]);
  const [requests, setRequests] = useState<StoreRequest[]>([]);
  const [agentCode, setAgentCode] = useState('');
  const [balance, setBalance] = useState('0.00');
  const [loading, setLoading] = useState(true);
  const [processing, setProcessing] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  async function loadDashboard() {
    const [productResponse, orderResponse, balanceResponse, storeResponse] = await Promise.all([
      api.get<Product[]>('/products'), api.get('/orders/mine'), api.get('/wallet/balance'), api.get('/agent-stores/me'),
    ]);
    setProducts(productResponse.data);
    setOrders(orderResponse.data);
    setBalance(String(balanceResponse.data.balance));
    setAgentCode(storeResponse.data.agentCode);
    setRequests(storeResponse.data.requests);
  }

  useEffect(() => {
    if (localStorage.getItem('role') !== 'AGENT') { router.replace('/'); return; }
    loadDashboard().catch(() => setMessage('Could not load the agent dashboard.')).finally(() => setLoading(false));
  }, [router]);

  const shopUrl = agentCode && typeof window !== 'undefined' ? `${window.location.origin}/shop/${agentCode}` : '';

  async function review(id: string, action: 'APPROVE' | 'HOLD' | 'REJECT') {
    const note = action === 'HOLD' || action === 'REJECT' ? window.prompt(`Optional note for ${action.toLowerCase()}:`) ?? undefined : undefined;
    setProcessing(id);
    setMessage(null);
    try {
      await api.patch(`/agent-stores/orders/${id}`, { action, note });
      await loadDashboard();
      setMessage(action === 'APPROVE' ? 'Order approved and sent for delivery.' : `Order marked ${action.toLowerCase()}.`);
    } catch (error: any) {
      const detail = error?.response?.data?.message;
      setMessage(detail === 'insufficient_balance' ? 'Wallet balance is too low to approve this order.' : 'Could not update this order. Please try again.');
    } finally { setProcessing(null); }
  }

  async function viewProof(id: string) {
    try {
      const response = await api.get(`/agent-stores/orders/${id}/proof`, { responseType: 'blob' });
      const url = URL.createObjectURL(response.data);
      window.open(url, '_blank', 'noopener,noreferrer');
      setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } catch { setMessage('Could not open the payment screenshot.'); }
  }

  if (loading) return <Panel>Loading agent dashboard…</Panel>;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div><div className="mb-2 inline-flex rounded-full bg-[var(--color-signal-tint)] px-3 py-1 text-xs font-semibold text-[var(--color-signal)]">Agent store active</div><h1 className="font-display text-2xl font-semibold">Agent Dashboard</h1><p className="text-sm text-[var(--color-ink-muted)]">Review customer payments before dispatching bundles.</p></div>
        <Link href="/" className="rounded-lg bg-[var(--color-gold)] px-4 py-2.5 font-display text-sm font-semibold text-white hover:bg-[var(--color-gold-dark)]">Buy for yourself</Link>
      </div>

      {message && <div className="rounded-xl bg-[var(--color-gold-tint)] px-4 py-3 text-sm text-[var(--color-gold-dark)]">{message}</div>}

      <AgentNotifications />

      {shopUrl && <section className="rounded-2xl border border-[var(--color-border)] bg-white p-5"><h2 className="font-display font-semibold">Your customer store</h2><p className="mt-1 text-sm text-[var(--color-ink-muted)]">Share this link. Customers submit their transaction ID or payment screenshot for your approval.</p><div className="mt-3 flex flex-col gap-2 sm:flex-row"><input readOnly value={shopUrl} onClick={(event) => event.currentTarget.select()} className="min-w-0 flex-1 rounded-lg border border-[var(--color-border-strong)] px-3 py-2.5 font-mono text-sm" /><button onClick={() => { navigator.clipboard.writeText(shopUrl); setMessage('Store link copied.'); }} className="rounded-lg bg-[var(--color-ink)] px-4 py-2.5 text-sm font-semibold text-white">Copy link</button><Link href={`/shop/${agentCode}`} className="rounded-lg border border-[var(--color-border-strong)] px-4 py-2.5 text-center text-sm font-semibold">Preview</Link></div></section>}

      <div className="grid gap-4 sm:grid-cols-3"><Stat label="Wallet balance" value={`₵${Number(balance).toFixed(2)}`} accent /><Stat label="Awaiting review" value={String(requests.filter((request) => request.status === 'SUBMITTED').length)} /><Stat label="Agent-priced bundles" value={String(products.filter((product) => product.hasAgentPrice).length)} /></div>

      <section className="overflow-x-auto rounded-2xl border border-[var(--color-border)] bg-white">
        <div className="border-b border-[var(--color-border)] p-4"><h2 className="font-display font-semibold">Customer payment requests</h2><p className="text-sm text-[var(--color-ink-muted)]">Verify the transaction before approving. Approval charges your wallet and sends the bundle.</p></div>
        <table className="w-full min-w-[920px] text-sm"><thead><tr className="border-b border-[var(--color-border)] text-left text-[var(--color-ink-faint)]"><th className="p-3 font-medium">Customer</th><th className="p-3 font-medium">Bundle</th><th className="p-3 font-medium">Recipient</th><th className="p-3 font-medium">Payment proof</th><th className="p-3 font-medium">Status</th><th className="p-3 font-medium">Actions</th></tr></thead><tbody>
          {requests.map((request) => <tr key={request.id} className="border-b border-[var(--color-border)] align-top last:border-0"><td className="p-3"><div className="font-medium">{request.customerName || 'Customer'}</div><div className="text-xs text-[var(--color-ink-faint)]">{request.customerPhone || new Date(request.createdAt).toLocaleString()}</div></td><td className="p-3">{request.product.network} {Number(request.product.bundleGb)}GB</td><td className="p-3 font-mono">{request.recipient}</td><td className="p-3"><div className="font-mono text-xs">{request.transactionId || 'No transaction ID'}</div>{request.hasProof && <button onClick={() => viewProof(request.id)} className="mt-1 text-xs font-semibold text-[var(--color-gold-dark)] underline">View screenshot</button>}</td><td className="p-3"><StatusBadge status={request.status} />{request.reviewNote && request.reviewNote !== 'approval_in_progress' && <div className="mt-1 max-w-40 text-xs text-[var(--color-ink-faint)]">{request.reviewNote}</div>}</td><td className="p-3"><div className="flex gap-1.5"><button disabled={processing === request.id || ['APPROVED','REJECTED'].includes(request.status)} onClick={() => review(request.id, 'APPROVE')} className="rounded-lg bg-[var(--color-signal)] px-2.5 py-1.5 text-xs font-semibold text-white disabled:opacity-40">Approve</button><button disabled={processing === request.id || ['APPROVED','REJECTED'].includes(request.status)} onClick={() => review(request.id, 'HOLD')} className="rounded-lg bg-[var(--color-gold)] px-2.5 py-1.5 text-xs font-semibold text-white disabled:opacity-40">Hold</button><button disabled={processing === request.id || ['APPROVED','REJECTED'].includes(request.status)} onClick={() => review(request.id, 'REJECT')} className="rounded-lg bg-[var(--color-alert)] px-2.5 py-1.5 text-xs font-semibold text-white disabled:opacity-40">Reject</button></div></td></tr>)}
          {requests.length === 0 && <tr><td colSpan={6} className="p-8 text-center text-[var(--color-ink-faint)]">No customer requests yet. Share your store link to begin.</td></tr>}
        </tbody></table>
      </section>

      <section className="rounded-2xl border border-[var(--color-border)] bg-white p-5"><div className="mb-4"><h2 className="font-display font-semibold">Your wholesale catalog</h2><p className="text-sm text-[var(--color-ink-muted)]">These prices are charged to your wallet when you approve a request.</p></div><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{products.map((product) => <div key={product.id} className="flex items-center gap-3 rounded-xl border border-[var(--color-border)] p-3"><NetworkLogo network={product.network} size={38} /><div className="min-w-0 flex-1"><div className="font-display font-semibold">{product.network} {Number(product.bundleGb)}GB</div><div className="font-mono text-sm text-[var(--color-gold-dark)]">₵{Number(product.price).toFixed(2)}</div></div>{product.hasAgentPrice && <span className="rounded-full bg-[var(--color-signal-tint)] px-2 py-1 text-[10px] font-semibold text-[var(--color-signal)]">Agent</span>}</div>)}</div></section>

      <section className="overflow-x-auto rounded-2xl border border-[var(--color-border)] bg-white"><div className="border-b border-[var(--color-border)] p-4 font-display font-semibold">Dispatched orders</div><table className="w-full min-w-[650px] text-sm"><thead><tr className="border-b border-[var(--color-border)] text-left text-[var(--color-ink-faint)]"><th className="p-3 font-medium">Bundle</th><th className="p-3 font-medium">Recipient</th><th className="p-3 font-medium">Cost</th><th className="p-3 font-medium">Status</th><th className="p-3 font-medium">Date</th></tr></thead><tbody>{orders.slice(0, 20).map((order) => <tr key={order.id} className="border-b border-[var(--color-border)] last:border-0"><td className="p-3">{order.product.network} {Number(order.product.bundleGb)}GB</td><td className="p-3 font-mono">{order.recipient}</td><td className="p-3 font-mono">₵{Number(order.amountCharged).toFixed(2)}</td><td className="p-3">{order.status}</td><td className="p-3 text-[var(--color-ink-faint)]">{new Date(order.createdAt).toLocaleDateString()}</td></tr>)}{orders.length === 0 && <tr><td colSpan={5} className="p-8 text-center text-[var(--color-ink-faint)]">No dispatched orders yet.</td></tr>}</tbody></table></section>
    </div>
  );
}

function Stat({ label, value, accent = false }: { label: string; value: string; accent?: boolean }) { return <div className="rounded-2xl border border-[var(--color-border)] bg-white p-4"><div className="text-xs text-[var(--color-ink-faint)]">{label}</div><div className={`font-display mt-1 text-2xl font-semibold ${accent ? 'text-[var(--color-signal)]' : ''}`}>{value}</div></div>; }
function StatusBadge({ status }: { status: ReviewStatus }) { const styles: Record<ReviewStatus,string>={SUBMITTED:'bg-[var(--color-gold-tint)] text-[var(--color-gold-dark)]',HOLD:'bg-[var(--color-paper-dim)] text-[var(--color-ink-muted)]',APPROVED:'bg-[var(--color-signal-tint)] text-[var(--color-signal)]',REJECTED:'bg-[var(--color-alert-tint)] text-[var(--color-alert)]'}; return <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${styles[status]}`}>{status}</span>; }
function Panel({ children }: { children: React.ReactNode }) { return <div className="rounded-2xl border border-[var(--color-border)] bg-white p-8 text-center text-sm text-[var(--color-ink-faint)]">{children}</div>; }
