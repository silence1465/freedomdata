'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { api, OrderTrackingInfo } from '@/lib/api';
import { getSocket } from '@/lib/socket';
import SignalStepper from '@/components/SignalStepper';

export default function TrackOrderPage() {
  const params = useParams<{ orderId: string }>();
  const orderId = params.orderId;
  const [order, setOrder] = useState<OrderTrackingInfo | null>(null);
  const [notFound, setNotFound] = useState(false);
  const [connected, setConnected] = useState(false);

  useEffect(() => {
    if (!orderId) return;

    api
      .get<OrderTrackingInfo>(`/orders/${orderId}`)
      .then((res) => setOrder(res.data))
      .catch(() => setNotFound(true));

    const socket = getSocket();
    socket.on('connect', () => setConnected(true));
    socket.on('disconnect', () => setConnected(false));
    socket.emit('subscribe:order', orderId);

    function onUpdate(payload: Partial<OrderTrackingInfo> & { id: string }) {
      if (payload.id !== orderId) return;
      setOrder((prev) => (prev ? { ...prev, ...payload } : (payload as OrderTrackingInfo)));
    }
    socket.on('order:update', onUpdate);

    return () => {
      socket.off('order:update', onUpdate);
    };
  }, [orderId]);

  if (notFound) {
    return (
      <div className="mx-auto max-w-md rounded-2xl border border-dashed border-[var(--color-border)] p-8 text-center">
        <p className="font-display font-semibold">Order not found</p>
        <p className="mt-1 text-sm text-[var(--color-ink-muted)]">
          Double-check the order ID from your receipt and try again.
        </p>
      </div>
    );
  }

  if (!order) {
    return (
      <div className="mx-auto max-w-md rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-8 text-center text-sm text-[var(--color-ink-faint)]">
        Loading order…
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-lg space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="font-display text-xl font-semibold">Order tracking</h1>
        <span className="flex items-center gap-1.5 text-xs text-[var(--color-ink-faint)]">
          <span
            className={`h-1.5 w-1.5 rounded-full ${connected ? 'bg-[var(--color-signal)]' : 'bg-[var(--color-ink-faint)]'}`}
          />
          {connected ? 'Live' : 'Reconnecting…'}
        </span>
      </div>

      <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-6">
        <SignalStepper status={order.status} />

        <div className="mt-6 space-y-2 border-t border-[var(--color-border)] pt-4 font-mono text-sm">
          <Row label="Order ID" value={order.id} />
          <Row label="Bundle" value={`${order.network} ${order.bundleGb}GB`} />
          <Row label="Recipient" value={order.recipient} />
        </div>
      </div>

      {order.status === 'REFUNDED' && (
        <p className="rounded-lg bg-[var(--color-signal-tint)] px-4 py-2.5 text-sm text-[var(--color-signal)]">
          Your wallet has been credited back for this order.
        </p>
      )}
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between">
      <span className="text-[var(--color-ink-faint)]">{label}</span>
      <span className="max-w-[60%] truncate text-right">{value}</span>
    </div>
  );
}
