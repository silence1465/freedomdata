'use client';

type Status = 'PENDING' | 'PROCESSING' | 'DELIVERED' | 'FAILED' | 'REFUNDED' | 'REFUND_PROCESSING';

const BARS_LIT: Record<Status, number> = {
  PENDING: 1,
  PROCESSING: 3,
  DELIVERED: 4,
  FAILED: 0,
  REFUNDED: 0,
  REFUND_PROCESSING: 0,
};

const STATUS_COPY: Record<Status, { title: string; detail: string }> = {
  PENDING: { title: 'Order received', detail: 'We\u2019ve charged your wallet and queued the order.' },
  PROCESSING: { title: 'Dispatching', detail: 'Sending the bundle to the network now.' },
  DELIVERED: { title: 'Delivered', detail: 'The bundle has landed on the recipient\u2019s line.' },
  FAILED: { title: 'Delivery failed', detail: 'The network could not complete this dispatch.' },
  REFUNDED: { title: 'Refunded', detail: 'The charge has been credited back to your wallet.' },
  REFUND_PROCESSING: { title: 'Refund in progress', detail: 'Your wallet credit is being processed.' },
};

/** The nav's signal-bars mark, repurposed as an order-status indicator: bars light
 *  up left to right as the order moves toward delivery, or turn to the alert
 *  palette on a failure/refund path. */
export default function SignalStepper({ status }: { status: Status }) {
  const isFailurePath = status === 'FAILED' || status === 'REFUNDED' || status === 'REFUND_PROCESSING';
  const lit = BARS_LIT[status];
  const copy = STATUS_COPY[status];

  return (
    <div className="flex items-center gap-4">
      <span className={`signal-bars ${isFailurePath ? 'lit-alert' : ''}`} aria-hidden>
        {[0, 1, 2, 3].map((i) => (
          <span key={i} className={`bar ${isFailurePath ? (i === 0 ? 'lit' : '') : i < lit ? 'lit' : ''}`} />
        ))}
      </span>
      <div>
        <div className="font-display text-base font-semibold">{copy.title}</div>
        <div className="text-sm text-[var(--color-ink-muted)]">{copy.detail}</div>
      </div>
    </div>
  );
}
