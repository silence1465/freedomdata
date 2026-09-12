import type { Metadata } from 'next';
import './globals.css';
import NavBar from '@/components/NavBar';

export const metadata: Metadata = {
  title: 'Freedom Data — Buy Data Bundles Instantly',
  description: 'Affordable MTN, Telecel and AirtelTigo data bundles with live delivery tracking.',
  applicationName: 'Freedom Data',
  manifest: '/manifest.webmanifest',
  appleWebApp: { capable: true, statusBarStyle: 'default', title: 'Freedom Data' },
  icons: { icon: '/icon-192.png', apple: '/icon-192.png' },
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link
          href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap"
          rel="stylesheet"
        />
      </head>
      <body className="min-h-screen">
        <NavBar />
        <main className="mx-auto min-h-[calc(100vh-150px)] max-w-6xl px-4 py-10 sm:px-6">{children}</main>
        <footer className="mt-16 border-t border-[var(--color-border)]">
          <div className="mx-auto max-w-6xl px-4 py-6 text-xs text-[var(--color-ink-faint)] sm:px-6">
            Freedom Data · Wallet purchases are non-refundable except where a dispatch fails and DataSika issues an automatic refund.
          </div>
        </footer>
      </body>
    </html>
  );
}
