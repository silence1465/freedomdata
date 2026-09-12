'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';

export default function LoginPage() {
  const router = useRouter();
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [name, setName] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const path = mode === 'login' ? '/auth/login' : '/auth/register';
      const body = mode === 'login' ? { phone, password } : { phone, password, name };
      const res = await api.post(path, body);
      localStorage.setItem('token', res.data.access_token);
      localStorage.setItem('userId', res.data.userId);
      localStorage.setItem('role', res.data.role);
      router.push('/');
      router.refresh();
    } catch (err: any) {
      const status = err?.response?.status;
      const code = err?.response?.data?.message ?? err?.response?.data?.code;

      if (!err?.response) {
        setError('Can\u2019t reach the server. Check that the backend is running and NEXT_PUBLIC_API_URL is correct.');
      } else if (status === 409 || code === 'phone_already_registered') {
        setError('That phone number is already registered — try logging in instead.');
      } else if (status === 401) {
        setError('That phone number or password doesn\u2019t match our records.');
      } else if (status === 400) {
        setError(`Check your details — ${JSON.stringify(err.response.data?.message ?? err.response.data)}`);
      } else {
        setError(`Something went wrong (status ${status ?? 'unknown'}). Check the backend logs for details.`);
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="mx-auto max-w-sm">
      <div className="mb-5 text-center">
        <span className="signal-bars mx-auto" aria-hidden style={{ justifyContent: 'center' }}>
          <span className="bar lit" />
          <span className="bar lit" />
          <span className="bar" />
          <span className="bar" />
        </span>
        <h1 className="font-display mt-3 text-xl font-semibold">
          {mode === 'login' ? 'Welcome back' : 'Create your account'}
        </h1>
      </div>

      <form onSubmit={submit} className="space-y-3 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5">
        {mode === 'register' && (
          <Field label="Full name">
            <input
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="w-full rounded-lg border border-[var(--color-border-strong)] bg-white px-3 py-2.5 outline-none focus:border-[var(--color-ink)]"
            />
          </Field>
        )}
        <Field label="Phone number">
          <input
            placeholder="0241234567"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            className="w-full rounded-lg border border-[var(--color-border-strong)] bg-white px-3 py-2.5 font-mono outline-none focus:border-[var(--color-ink)]"
          />
        </Field>
        <Field label="Password">
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="w-full rounded-lg border border-[var(--color-border-strong)] bg-white px-3 py-2.5 outline-none focus:border-[var(--color-ink)]"
          />
        </Field>

        {error && (
          <p className="rounded-lg bg-[var(--color-alert-tint)] px-3 py-2 text-sm text-[var(--color-alert)]">
            {error}
          </p>
        )}

        <button
          disabled={loading}
          className="w-full rounded-lg bg-[var(--color-gold)] py-2.5 font-display font-semibold text-white hover:bg-[var(--color-gold-dark)] disabled:opacity-50"
        >
          {loading ? 'Please wait…' : mode === 'login' ? 'Log in' : 'Sign up'}
        </button>
        <button
          type="button"
          onClick={() => setMode(mode === 'login' ? 'register' : 'login')}
          className="w-full text-center text-sm text-[var(--color-ink-muted)] hover:text-[var(--color-ink)]"
        >
          {mode === 'login' ? "Don't have an account? Sign up" : 'Already have an account? Log in'}
        </button>
      </form>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="mb-1 block text-sm font-medium">{label}</label>
      {children}
    </div>
  );
}
