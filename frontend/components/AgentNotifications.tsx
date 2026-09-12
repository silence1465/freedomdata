'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';

function decodeKey(value: string) {
  const padding = '='.repeat((4 - value.length % 4) % 4);
  const raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
  return Uint8Array.from([...raw].map((character) => character.charCodeAt(0)));
}

export default function AgentNotifications() {
  const [supported, setSupported] = useState(true);
  const [enabled, setEnabled] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('Get an alert as soon as a customer submits payment.');

  useEffect(() => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      setSupported(false); setMessage('Push notifications are not supported by this browser.'); return;
    }
    navigator.serviceWorker.register('/sw.js')
      .then((registration) => registration.pushManager.getSubscription())
      .then((subscription) => setEnabled(Boolean(subscription)))
      .catch(() => setMessage('The notification service could not be started.'));
  }, []);

  async function enable() {
    setBusy(true);
    try {
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') { setMessage('Notification permission was not granted. You can enable it in browser settings.'); return; }
      const config = await api.get('/agent-stores/notifications/config');
      if (!config.data.enabled || !config.data.publicKey) throw new Error('push_not_configured');
      const registration = await navigator.serviceWorker.ready;
      const current = await registration.pushManager.getSubscription();
      const subscription = current || await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: decodeKey(config.data.publicKey) });
      await api.post('/agent-stores/notifications/subscribe', subscription.toJSON());
      setEnabled(true); setMessage('Notifications are active on this device.');
    } catch { setMessage('Could not enable notifications. Use HTTPS or localhost and try again.'); }
    finally { setBusy(false); }
  }

  async function disable() {
    setBusy(true);
    try {
      const registration = await navigator.serviceWorker.ready;
      const subscription = await registration.pushManager.getSubscription();
      if (subscription) {
        await api.delete('/agent-stores/notifications/subscribe', { data: { endpoint: subscription.endpoint } });
        await subscription.unsubscribe();
      }
      setEnabled(false); setMessage('Notifications are off on this device.');
    } catch { setMessage('Could not turn off notifications. Please try again.'); }
    finally { setBusy(false); }
  }

  return (
    <section className="flex flex-col gap-3 rounded-2xl border border-[var(--color-border)] bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
      <div><div className="flex items-center gap-2"><span aria-hidden="true">🔔</span><h2 className="font-display font-semibold">Payment alerts</h2>{enabled && <span className="rounded-full bg-[var(--color-signal-tint)] px-2 py-0.5 text-[10px] font-semibold text-[var(--color-signal)]">ACTIVE</span>}</div><p className="mt-1 text-sm text-[var(--color-ink-muted)]">{message}</p></div>
      {supported && <button disabled={busy} onClick={enabled ? disable : enable} className={`rounded-lg px-4 py-2.5 text-sm font-semibold disabled:opacity-50 ${enabled ? 'border border-[var(--color-border-strong)]' : 'bg-[var(--color-signal)] text-white'}`}>{busy ? 'Please wait…' : enabled ? 'Turn off' : 'Enable alerts'}</button>}
    </section>
  );
}
