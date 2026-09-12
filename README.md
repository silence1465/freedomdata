# DataSika Reseller Platform

A full reseller system on top of the DataSika Developer API v2: customer storefront with
your own margins, a real-time order tracker (Socket.io — no SMS), an internal wallet with
a full ledger, and an admin dashboard.

**Stack:** NestJS 11 + Prisma 7 + MySQL + BullMQ 5 (Redis) + Socket.io 4 · Next.js 16 + React 19 + Tailwind 4 · Paystack

All dependency versions in `backend/package.json` and `frontend/package.json` were checked
against the npm registry at build time and confirmed to install with zero peer-dependency
conflicts. The frontend was additionally run through a full `next build` and passed. The
backend could not run `prisma generate` in this sandbox (the engine download host isn't on
its network allowlist) — this will work normally in your own environment; see step 3 below.

## 1. Prerequisites

- Node.js 20+
- A MySQL database (local, PlanetScale, RDS, etc.)
- A Redis instance (local, Upstash, Redis Cloud, etc.) — required for BullMQ
- A DataSika API key (`dsk_live_...`)
- A Paystack account (test keys are fine to start)

## 2. Backend setup

```bash
cd backend
npm install
cp .env.example .env
# edit .env: DATABASE_URL, REDIS_HOST/PORT, JWT_SECRET, DATASIKA_API_KEY, PAYSTACK_SECRET_KEY
```

## 3. Database

```bash
npx prisma generate
npx prisma migrate dev --name init
```

This creates the `User`, `Product`, `Order`, and `WalletLedger` tables in your MySQL database.

> **Note on Prisma 7:** this is a very recent major version with a real breaking change —
> connection URLs no longer live in `schema.prisma`. They're read from `prisma.config.ts`
> (already included in `backend/`) for the CLI/migrate commands, and `PrismaService` builds
> a `@prisma/adapter-mariadb` driver adapter from `DATABASE_URL` at runtime for the app
> itself. You don't need to do anything extra beyond setting `DATABASE_URL` in `.env` —
> just flagging it in case you're used to Prisma 6 and the shape looks unfamiliar.

## 4. Run the backend

```bash
npm run start:dev
```

This starts the API on `http://localhost:4000/api`, connects to Redis for the BullMQ
queues, and immediately schedules a repeating catalog-sync job (every 10 minutes) that
pulls DataSika's `/api-catalog` and upserts it into your `Product` table with your markup
applied on first sync.

**First-time setup:** create an account via `POST /api/auth/register`, then manually
promote it to `ADMIN` in the database (`UPDATE User SET role='ADMIN' WHERE phone='...'`)
so you can trigger `/api/products/sync` and set custom prices from the admin dashboard.

## 5. Frontend setup

```bash
cd frontend
npm install
```

Create `frontend/.env.local`:

```
NEXT_PUBLIC_API_URL=http://localhost:4000/api
NEXT_PUBLIC_SOCKET_URL=http://localhost:4000
```

```bash
npm run dev
```

Visit `http://localhost:3000`.

## 6. Paystack webhook

In your Paystack dashboard, point the webhook URL to:
`https://your-backend-domain.com/api/payments/webhook/paystack`

This is what actually credits a customer's wallet after a top-up — the frontend redirect
callback is not trusted for this, since it can be closed or interrupted mid-flow.

## How the real-time tracking works

1. Customer buys a bundle → backend debits their wallet, calls DataSika's buy endpoint
   with a deterministic `Idempotency-Key`, and stores the order as `PENDING`.
2. A BullMQ job is enqueued to poll `/api-order-status` after 15s.
3. Each poll either finds no change (re-enqueues with exponential backoff, capped at 60s)
   or finds a status change — which it persists and pushes immediately over Socket.io to
   anyone subscribed to that `order:<id>` or `user:<id>` room.
4. The tracking page (`/track/[orderId]`) subscribes on load and updates live, with one
   REST fallback fetch so it's never blank on first paint or after a reconnect.
5. If DataSika refunds a failed dispatch, the processor automatically credits the
   customer's wallet too, so you're never out of sync between the two ledgers.

## What's scaffolded vs. what you should still add before going to production

**Included:** auth (JWT), catalog sync with markup pricing, wallet + ledger, order
placement + async tracking, Paystack top-ups, a bare-bones admin dashboard.

**Worth adding next:**
- MTN Express support in `datasika.client.ts#buyExpress` is a best-effort placeholder —
  DataSika's docs don't publish the `/api-buy-express` request/response shape, only that
  it exists (`use_express_endpoint` error) and returns `202` when uncertain. Confirm the
  actual contract with DataSika before relying on it.
- Rate-limit-aware request queuing on the buy side too (currently only the status poller
  respects `retry_after`).
- Bulk/CSV ordering for agents, sub-accounts, and a proper reconciliation report
  (what you charged customers vs. what DataSika charged you).
- Production hardening: input validation DTOs with `class-validator`, refresh tokens,
  request logging, and moving the admin role check off a manual SQL update.
