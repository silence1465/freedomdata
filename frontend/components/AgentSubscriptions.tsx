'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';

interface Agent {
  id: string;
  name?: string;
  phone: string;
  email?: string;
  agentCode?: string;
  walletBalance: string;
  agentSubscriptionExpiresAt?: string;
  _count: { agentStoreOrders: number };
}

export default function AgentSubscriptions() {
  const [agents, setAgents] = useState<Agent[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  async function load() {
    const response = await api.get<Agent[]>('/admin/agents');
    setAgents(response.data);
  }

  useEffect(() => { load().catch(() => setMessage('Could not load agents.')).finally(() => setLoading(false)); }, []);

  async function extend(agent: Agent) {
    const entered = window.prompt(`How many days should be added for ${agent.name || agent.phone}?`, '30');
    if (entered === null) return;
    const days = Number(entered);
    if (!Number.isInteger(days) || days < 1 || days > 3650) { setMessage('Enter a whole number from 1 to 3650.'); return; }
    setBusy(agent.id); setMessage(null);
    try {
      await api.patch(`/admin/agents/${agent.id}/subscription`, { days });
      await load();
      setMessage(`${days} days added to ${agent.name || agent.phone}.`);
    } catch { setMessage('Could not extend this subscription.'); }
    finally { setBusy(null); }
  }

  if (loading) return <div className="rounded-2xl border border-[var(--color-border)] bg-white p-8 text-center text-sm text-[var(--color-ink-faint)]">Loading agents…</div>;

  return <div className="space-y-3">
    {message && <div className="rounded-xl bg-[var(--color-gold-tint)] px-4 py-3 text-sm text-[var(--color-gold-dark)]">{message}</div>}
    <div className="overflow-x-auto rounded-2xl border border-[var(--color-border)] bg-white">
      <table className="w-full min-w-[760px] text-sm"><thead><tr className="border-b border-[var(--color-border)] text-left text-[var(--color-ink-faint)]"><th className="p-3 font-medium">Agent</th><th className="p-3 font-medium">Store</th><th className="p-3 font-medium">Orders</th><th className="p-3 font-medium">Wallet</th><th className="p-3 font-medium">Subscription</th><th className="p-3 font-medium">Action</th></tr></thead><tbody>
        {agents.map((agent) => { const expiry = agent.agentSubscriptionExpiresAt ? new Date(agent.agentSubscriptionExpiresAt) : null; const active = Boolean(expiry && expiry > new Date()); return <tr key={agent.id} className="border-b border-[var(--color-border)] last:border-0"><td className="p-3"><div className="font-medium">{agent.name || 'Unnamed agent'}</div><div className="text-xs text-[var(--color-ink-faint)]">{agent.phone}</div></td><td className="p-3 font-mono text-xs">{agent.agentCode || 'Not opened'}</td><td className="p-3">{agent._count.agentStoreOrders}</td><td className="p-3 font-mono">GHS {Number(agent.walletBalance).toFixed(2)}</td><td className="p-3"><span className={`rounded-full px-2 py-1 text-xs font-semibold ${active ? 'bg-[var(--color-signal-tint)] text-[var(--color-signal)]' : 'bg-[var(--color-alert-tint)] text-[var(--color-alert)]'}`}>{active ? `Active until ${expiry!.toLocaleDateString()}` : 'Expired'}</span></td><td className="p-3"><button disabled={busy === agent.id} onClick={() => extend(agent)} className="rounded-lg bg-[var(--color-gold)] px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">{busy === agent.id ? 'Extending…' : 'Extend'}</button></td></tr>; })}
        {agents.length === 0 && <tr><td colSpan={6} className="p-8 text-center text-[var(--color-ink-faint)]">No agent accounts found.</td></tr>}
      </tbody></table>
    </div>
  </div>;
}
