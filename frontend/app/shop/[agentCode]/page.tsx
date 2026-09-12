'use client';

import { use, useEffect, useMemo, useState } from 'react';
import NetworkLogo from '@/components/NetworkLogo';
import { api } from '@/lib/api';

interface ShopProduct { id: string; network: string; bundleGb: string; serviceType: string; price: string }
interface ShopData { agent: { name: string; code: string }; products: ShopProduct[] }

export default function AgentShopPage({ params }: { params: Promise<{ agentCode: string }> }) {
  const { agentCode } = use(params);
  const [store, setStore] = useState<ShopData | null>(null);
  const [selected, setSelected] = useState<ShopProduct | null>(null);
  const [recipient, setRecipient] = useState('');
  const [customerName, setCustomerName] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [transactionId, setTransactionId] = useState('');
  const [proof, setProof] = useState<File | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [submittedId, setSubmittedId] = useState<string | null>(null);

  useEffect(() => {
    api.get(`/agent-stores/${agentCode}`).then((response) => setStore(response.data)).catch(() => setError('This agent store is unavailable.')).finally(() => setLoading(false));
  }, [agentCode]);

  const groups = useMemo(() => Object.entries((store?.products ?? []).reduce<Record<string, ShopProduct[]>>((result, product) => { (result[product.network] ??= []).push(product); return result; }, {})), [store]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!selected) { setError('Select a bundle first.'); return; }
    if (!/^0\d{9}$/.test(recipient)) { setError('Enter a valid recipient number.'); return; }
    if (!transactionId.trim() && !proof) { setError('Enter the transaction ID or upload a payment screenshot.'); return; }

    const form = new FormData();
    form.append('productId', selected.id);
    form.append('recipient', recipient);
    if (customerName.trim()) form.append('customerName', customerName.trim());
    if (customerPhone) form.append('customerPhone', customerPhone);
    if (transactionId.trim()) form.append('transactionId', transactionId.trim());
    if (proof) form.append('proof', proof);

    setSubmitting(true); setError(null);
    try {
      const response = await api.post(`/agent-stores/${agentCode}/orders`, form);
      setSubmittedId(response.data.id);
    } catch (requestError: any) {
      const message = requestError?.response?.data?.message;
      setError(message === 'transaction_id_already_submitted' ? 'This transaction ID has already been submitted.' : 'Could not submit this order. Check the details and try again.');
    } finally { setSubmitting(false); }
  }

  if (loading) return <Notice>Loading agent store…</Notice>;
  if (!store) return <Notice>{error ?? 'Agent store not found.'}</Notice>;
  if (submittedId) return <div className="mx-auto max-w-lg rounded-2xl border border-[var(--color-border)] bg-white p-8 text-center"><div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[var(--color-signal-tint)] text-2xl text-[var(--color-signal)]">✓</div><h1 className="font-display mt-4 text-2xl font-semibold">Order sent for review</h1><p className="mt-2 text-sm text-[var(--color-ink-muted)]">{store.agent.name} will verify your payment and approve, hold, or reject the request.</p><p className="mt-4 font-mono text-xs text-[var(--color-ink-faint)]">Reference: {submittedId}</p></div>;

  return (
    <div className="space-y-8">
      <section className="rounded-3xl bg-[var(--color-ink)] px-6 py-8 text-white sm:px-8"><div className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--color-gold)]">Freedom Data agent store</div><h1 className="font-display mt-2 text-3xl font-semibold">{store.agent.name}</h1><p className="mt-2 max-w-xl text-sm text-white/65">Choose a bundle, make payment to the agent, then provide your transaction ID or upload the payment screenshot for approval.</p></section>

      <div className="grid gap-8 lg:grid-cols-3">
        <div className="space-y-5 lg:col-span-2"><h2 className="font-display text-lg font-semibold">Choose a bundle</h2>{groups.map(([network, products]) => <section key={network} className="overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-paper-dim)]"><header className="flex items-center gap-3 px-4 py-3"><NetworkLogo network={network} size={40} /><div><div className="font-display font-semibold">{network}</div><div className="text-xs text-[var(--color-ink-muted)]">{products.length} bundles available</div></div></header><div className="grid grid-cols-2 gap-2.5 p-3 pt-0 sm:grid-cols-3">{products.map((product) => <button type="button" key={product.id} onClick={() => { setSelected(product); setError(null); }} className={`rounded-xl border bg-white p-4 text-left shadow-sm transition ${selected?.id === product.id ? 'border-[var(--color-ink)] ring-2 ring-black/10' : 'border-[var(--color-border)] hover:-translate-y-0.5 hover:shadow-md'}`}><div className="font-display text-2xl font-bold">{Number(product.bundleGb)}<span className="text-sm text-[var(--color-ink-muted)]">GB</span></div><div className="mt-1 font-mono font-semibold text-[var(--color-gold-dark)]">₵{Number(product.price).toFixed(2)}</div></button>)}</div></section>)}</div>

        <form onSubmit={submit} className="h-fit space-y-4 rounded-2xl border border-[var(--color-border)] bg-white p-5 shadow-sm lg:sticky lg:top-6"><h2 className="font-display text-lg font-semibold">Submit your order</h2>{selected ? <div className="rounded-xl bg-[var(--color-gold-tint)] px-4 py-3 text-center"><div className="font-display font-bold">{selected.network} {Number(selected.bundleGb)}GB</div><div className="font-mono font-semibold text-[var(--color-gold-dark)]">₵{Number(selected.price).toFixed(2)}</div></div> : <div className="rounded-xl border-2 border-dashed border-[var(--color-border-strong)] bg-[var(--color-paper-dim)] px-4 py-4 text-center text-sm text-[var(--color-ink-faint)]">Select a bundle to continue</div>}
          <Field label="Recipient phone number"><PhoneInput value={recipient} onChange={setRecipient} placeholder="0241234567" required /></Field>
          <Field label="Your name (optional)"><input value={customerName} onChange={(e) => setCustomerName(e.target.value)} maxLength={100} className="form-control" /></Field>
          <Field label="Your phone (optional)"><PhoneInput value={customerPhone} onChange={setCustomerPhone} placeholder="For agent follow-up" /></Field>
          <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-paper-dim)] p-3"><p className="text-sm font-medium">Payment confirmation</p><p className="mb-3 text-xs text-[var(--color-ink-muted)]">Provide at least one of the options below.</p><Field label="Transaction ID"><input value={transactionId} onChange={(e) => setTransactionId(e.target.value)} maxLength={191} placeholder="e.g. 1234567890" className="form-control font-mono" /></Field><div className="my-2 text-center text-xs text-[var(--color-ink-faint)]">or</div><Field label="Upload screenshot"><input type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => setProof(e.target.files?.[0] ?? null)} className="block w-full text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:font-semibold" /></Field></div>
          {error && <p className="rounded-lg bg-[var(--color-alert-tint)] px-3 py-2 text-sm text-[var(--color-alert)]">{error}</p>}
          <button disabled={!selected || submitting} className="w-full rounded-lg bg-[var(--color-gold)] py-3 font-display font-semibold text-white hover:bg-[var(--color-gold-dark)] disabled:opacity-40">{submitting ? 'Submitting…' : 'Send for agent approval'}</button><p className="text-center text-[11px] text-[var(--color-ink-faint)]">The bundle is dispatched only after the agent verifies payment.</p>
        </form>
      </div>
      <style jsx>{`.form-control{width:100%;border:1px solid var(--color-border-strong);border-radius:8px;padding:10px 12px;background:#fff;outline:none}.form-control:focus{border-color:var(--color-ink)}`}</style>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) { return <label className="block"><span className="mb-1 block text-sm font-medium">{label}</span>{children}</label>; }
function PhoneInput({ value, onChange, placeholder, required = false }: { value: string; onChange: (value: string) => void; placeholder: string; required?: boolean }) { return <input type="tel" inputMode="numeric" maxLength={10} required={required} value={value} onChange={(e) => onChange(e.target.value.replace(/\D/g, ''))} placeholder={placeholder} className="form-control font-mono" />; }
function Notice({ children }: { children: React.ReactNode }) { return <div className="mx-auto max-w-lg rounded-2xl border border-[var(--color-border)] bg-white p-8 text-center text-[var(--color-ink-muted)]">{children}</div>; }
