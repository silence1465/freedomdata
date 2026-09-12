'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';

interface LedgerEntry {
  id: string;
  type: 'TOPUP' | 'PURCHASE' | 'REFUND' | 'ADJUSTMENT';
  amount: string;
  balanceAfter: string;
  createdAt: string;
}

export default function WalletPage() {
  const router = useRouter();
  const [balance, setBalance] = useState('0.00');
  const [history, setHistory] = useState<LedgerEntry[]>([]);
  const [amount, setAmount] = useState('');
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!localStorage.getItem('token')) {
      router.replace('/login');
      return;
    }
    Promise.all([api.get('/wallet/balance'), api.get('/wallet/history')])
      .then(([balanceResponse, historyResponse]) => {
        setBalance(String(balanceResponse.data.balance));
        setHistory(historyResponse.data);
      })
      .catch(() => setError('Could not load your wallet. Please log in again.'))
      .finally(() => setLoading(false));
  }, [router]);

  async function topUp(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const response = await api.post('/payments/topup/initiate', { amount: Number(amount) });
      window.location.href = response.data.authorization_url;
    } catch {
      setError('Could not start the payment. Check the amount and try again.');
      setSubmitting(false);
    }
  }

  if (loading) return <PanelMessage>Loading wallet…</PanelMessage>;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-display text-2xl font-semibold">Wallet</h1>
        <p className="text-sm text-[var(--color-ink-muted)]">Fund your account and review every balance movement.</p>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <div className="rounded-2xl border border-[var(--color-border)] bg-white p-5 md:col-span-1">
          <p className="text-xs text-[var(--color-ink-faint)]">Available balance</p>
          <p className="font-display mt-1 text-3xl font-semibold text-[var(--color-signal)]">₵{Number(balance).toFixed(2)}</p>
        </div>
        <form onSubmit={topUp} className="rounded-2xl border border-[var(--color-border)] bg-white p-5 md:col-span-2">
          <h2 className="font-display font-semibold">Top up with MoMo or card</h2>
          <div className="mt-3 flex flex-col gap-2 sm:flex-row">
            <div className="relative flex-1">
              <span className="absolute left-3 top-1/2 -translate-y-1/2 font-mono text-[var(--color-ink-faint)]">₵</span>
              <input type="number" min="1" max="100000" step="0.01" required value={amount} onChange={(e) => setAmount(e.target.value)} placeholder="Amount" className="w-full rounded-lg border border-[var(--color-border-strong)] py-2.5 pl-8 pr-3 outline-none focus:border-[var(--color-ink)]" />
            </div>
            <button disabled={submitting} className="rounded-lg bg-[var(--color-gold)] px-5 py-2.5 font-display font-semibold text-white hover:bg-[var(--color-gold-dark)] disabled:opacity-50">{submitting ? 'Opening…' : 'Add money'}</button>
          </div>
          {error && <p className="mt-3 text-sm text-[var(--color-alert)]">{error}</p>}
        </form>
      </div>

      <div className="overflow-x-auto rounded-2xl border border-[var(--color-border)] bg-white">
        <div className="border-b border-[var(--color-border)] p-4 font-display font-semibold">Transaction history</div>
        <table className="w-full min-w-[560px] text-sm">
          <thead><tr className="border-b border-[var(--color-border)] text-left text-[var(--color-ink-faint)]"><th className="p-3 font-medium">Type</th><th className="p-3 font-medium">Amount</th><th className="p-3 font-medium">Balance</th><th className="p-3 font-medium">Date</th></tr></thead>
          <tbody>
            {history.map((entry) => <tr key={entry.id} className="border-b border-[var(--color-border)] last:border-0"><td className="p-3 font-medium">{entry.type}</td><td className={`p-3 font-mono ${Number(entry.amount) >= 0 ? 'text-[var(--color-signal)]' : 'text-[var(--color-alert)]'}`}>{Number(entry.amount) >= 0 ? '+' : '−'}₵{Math.abs(Number(entry.amount)).toFixed(2)}</td><td className="p-3 font-mono">₵{Number(entry.balanceAfter).toFixed(2)}</td><td className="p-3 text-[var(--color-ink-faint)]">{new Date(entry.createdAt).toLocaleString()}</td></tr>)}
            {history.length === 0 && <tr><td colSpan={4} className="p-8 text-center text-[var(--color-ink-faint)]">No wallet activity yet.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function PanelMessage({ children }: { children: React.ReactNode }) {
  return <div className="rounded-2xl border border-[var(--color-border)] bg-white p-8 text-center text-sm text-[var(--color-ink-faint)]">{children}</div>;
}
