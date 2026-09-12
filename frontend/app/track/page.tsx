'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

export default function TrackLookupPage() {
  const router = useRouter();
  const [orderId, setOrderId] = useState('');

  return (
    <div className="mx-auto max-w-md">
      <h1 className="font-display text-xl font-semibold">Track your order</h1>
      <p className="mt-1 text-sm text-[var(--color-ink-muted)]">
        Paste the order ID from your receipt to see live delivery status.
      </p>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          if (orderId.trim()) router.push(`/track/${orderId.trim()}`);
        }}
        className="mt-5 space-y-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5"
      >
        <div>
          <label htmlFor="orderId" className="mb-1 block text-sm font-medium">
            Order ID
          </label>
          <input
            id="orderId"
            value={orderId}
            onChange={(e) => setOrderId(e.target.value)}
            placeholder="e.g. 3f9a2b7c-1d..."
            className="w-full rounded-lg border border-[var(--color-border-strong)] bg-white px-3 py-2.5 font-mono text-sm outline-none focus:border-[var(--color-ink)]"
          />
        </div>
        <button className="w-full rounded-lg bg-[var(--color-gold)] py-2.5 font-display font-semibold text-white hover:bg-[var(--color-gold-dark)]">
          Track order
        </button>
      </form>
    </div>
  );
}
