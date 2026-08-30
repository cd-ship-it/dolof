# Dolos — Deacons Ordination Lunch Ordering Form Ordering System

PHP + MySQL + Tailwind (Play CDN). Public form takes name / email / phone (formatted
live as a US number) / campus (San Leandro, Milpitas, Pleasanton, Tracy) / Lift Group
Name, lets the orderer pick lunch boxes **A–E** (1–10 each), and **requires online
payment via Stripe Checkout** before an order is confirmed. Each box set has a hard cap
(default 100) that is never oversold, even under concurrent submissions.

## How the capacity guard works

1. On "Pay with card", `create-checkout.php` takes a MySQL named lock per selected box
   (`GET_LOCK('dolos_box:<code>')`, acquired in code order), re-checks live capacity
   (`paid` + un-expired `pending` quantities), writes a `pending` order with a
   `hold_expires_at` 30 minutes out, releases the locks, then opens a Stripe Checkout
   Session.
2. `stripe-webhook.php` (`checkout.session.completed`) and `success.php` both call
   `order_finalize_payment()`, which locks the order row `FOR UPDATE`, flips it to
   `paid` once, and claims the confirmation email atomically (sent exactly once).
3. `checkout.session.expired` (webhook) and `scripts/cleanup-expired-orders.php` (cron)
   release holds that never paid. Expired/pending rows simply stop counting toward the
   cap via the `hold_expires_at > NOW()` clause.

## Local setup (MAMP)

```bash
composer install
/Applications/MAMP/Library/bin/mysql -h127.0.0.1 -P8889 -uroot -proot < sql/dev_bootstrap.sql
# serve at http://localhost:8888/dolof  (symlink the project into the web root):
ln -sfn "$(pwd)" ~/projects/dolof
```

Then open <http://localhost:8888/dolof/order>. Pay with Stripe test card
`4242 4242 4242 4242`, any future expiry / CVC.

`.env` holds all secrets (DB, Stripe keys, Google OAuth). `DOLOS_DEFAULT_BOX_PRICE`
($15) and `DOLOS_DEFAULT_BOX_CAP` (100) seed new installs; per-box price/cap are then
edited on the admin dashboard.

`data/life-groups.json` maps each campus to its life-group names. Picking a campus on
the form populates a type-ahead (`<datalist>`) for the Lift Group Name field; the field
stays free text, so anything not in the list is still accepted. Edit that JSON to
update the lists — no code or DB change needed.

## Admin

`/admin` → "Sign in with Google". Only emails in `ADMIN_WHITELIST` (`.env`) are allowed.
Reuses the SummerCamp Google OAuth client — add these redirect URIs to it:

- `http://localhost:8888/dolof/admin/google-callback`
- `https://crosspointchurchsv.org/dolof/admin/google-callback`

Dashboard: live paid / held / remaining per box, revenue, open/close ordering, edit box
names + prices + caps, edit event details. `/admin/orders` lists orders (filter by
status / box); `/admin/export` downloads paid orders as CSV.

## Production deployment (shared host)

1. Upload to a web directory named `dolof/`; run `composer install --no-dev`.
2. Run `sql/production.sql` against `crossp11_db1` (no `CREATE DATABASE`; idempotent).
   If upgrading a database created before a schema change, also run the relevant file
   in `sql/migrations/`.
3. Server `.env`: switch to the production DB block, `APP_ENV=production`,
   `APP_URL=https://crosspointchurchsv.org/dolof`, live Stripe keys + webhook secret,
   `ADMIN_WHITELIST`.
4. Stripe Dashboard → add webhook `https://crosspointchurchsv.org/dolof/stripe-webhook`
   for `checkout.session.completed` and `checkout.session.expired`; copy the signing
   secret into `.env` as `StripeWebhookSecret`.
5. Google Cloud Console → add the production redirect URI.
6. Cron: `*/5 * * * * php /path/to/dolof/scripts/cleanup-expired-orders.php >> /path/to/dolof/logs/cron.log 2>&1`
7. Ensure `logs/` is writable and not publicly served (the shipped `.htaccess` blocks
   `.env`, `includes/`, `sql/`, `scripts/`, `logs/`).

## Layout

| Path | Purpose |
|---|---|
| `order.php` / `create-checkout.php` | public form + Stripe Checkout hand-off |
| `success.php` / `cancel.php` | Stripe return targets |
| `stripe-webhook.php` | payment confirmation + hold release |
| `remaining-counts.php` | JSON availability (polled by the form) |
| `includes/boxes.php` / `orders.php` / `mailer.php` | capacity, order lifecycle, finalize + email |
| `admin/` | Google-auth dashboard, orders, CSV export |
| `scripts/cleanup-expired-orders.php` | cron reconcile / release |
| `sql/` | `dev_bootstrap.sql`, `production.sql` |
