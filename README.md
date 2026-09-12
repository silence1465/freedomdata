# Kwansika Data — Complete Reseller Platform

A single Laravel app: data bundles (mock DataSika), USDT buy/sell desk, wallet with
ledger, admin dashboard. Blade views + Vite + Tailwind 4 + MySQL.

## Prerequisites

- PHP 8.2+ with extensions: mbstring, xml, curl, mysql
- Composer
- MySQL (XAMPP works)
- Node.js 18+ (for Vite/Tailwind)

## Setup (5 minutes)

```bash
# 1. Create the Laravel skeleton
composer create-project laravel/laravel kwansika-app
cd kwansika-app

# 2. Copy ALL the files from this zip into the new project, overwriting where needed:
#    app/         -> kwansika-app/app/
#    database/    -> kwansika-app/database/     (ADD to existing migrations, don't delete theirs)
#    resources/   -> kwansika-app/resources/
#    routes/web.php -> kwansika-app/routes/web.php (overwrite)
#    bootstrap/app.php -> kwansika-app/bootstrap/app.php (overwrite)
#    vite.config.js -> kwansika-app/vite.config.js (overwrite)
#    package.json -> kwansika-app/package.json (overwrite)
#
#    config/services.snippet.php -> MERGE into kwansika-app/config/services.php
#    (add the 'datasika' and 'paystack' arrays into the existing return array)

# 3. Configure .env
#    DB_DATABASE=kwansika
#    DB_USERNAME=root
#    DB_PASSWORD=          (empty for XAMPP default)
#    DATASIKA_API_KEY=mock (this enables mock mode — no real API key needed)

# 4. Create the database in phpMyAdmin: kwansika

# 5. Install everything
composer require doctrine/dbal   # needed for the ->change() in the users migration
php artisan key:generate
php artisan migrate
npm install
npm run build                    # or npm run dev for hot reload

# 6. Start
php artisan serve
# Visit http://localhost:8000
```

## First steps after setup

1. Register an account at /register
2. Promote yourself to admin:
   ```bash
   php artisan tinker
   >>> App\Models\User::where('phone', '0241234567')->update(['role' => 'ADMIN']);
   ```
3. Top up your wallet at /wallet (manual topup for testing)
4. Buy a data bundle from the homepage — it uses mock DataSika and auto-delivers
5. Check /admin for the full dashboard, /admin/pricing for bundle prices,
   /admin/crypto for the USDT desk

## Mock mode

With `DATASIKA_API_KEY=mock` in .env, all data bundle purchases succeed instantly
with a fake DataSika order ID and auto-deliver. When you get a real API key later,
just replace `mock` with `dsk_live_...` in .env and restart — the same code path
will call the real DataSika API.

## Pages

Customer:
- `/`          — storefront (browse + buy bundles)
- `/crypto`    — buy/sell USDT
- `/track`     — order lookup
- `/track/:id` — live order tracking (polls every 4s)
- `/wallet`    — balance + transaction history + manual topup
- `/login`     — log in
- `/register`  — sign up

Admin (only visible when role=ADMIN):
- `/admin`         — dashboard with stats + order ledger
- `/admin/pricing` — edit sell/agent price per bundle
- `/admin/crypto`  — USDT rate settings + confirm/cancel orders
