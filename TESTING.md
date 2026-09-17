# Dolos — End-to-End Testing Plan

Manual test plan for the Deacons Ordination Lunch box-ordering app. There is no
automated test suite (plain PHP, no PHPUnit) — this is the checklist to run
locally before each deploy, and in full before go-live on the production
`.env` window (`ORDERING_START` / `ORDERING_END`).

Run against MAMP locally unless a step says "production only." Use Stripe
**test mode** keys everywhere except the final production smoke test.

---

## 0. Environment setup

- [ ] `composer install`; `npm install` if `package.json` scripts are used for CSS.
- [ ] `mysql -h127.0.0.1 -P8889 -uroot -proot < sql/dev_bootstrap.sql` (fresh schema + seed boxes/settings).
- [ ] `.env` present with **test-mode** `StripePublicKey` / `StripeSecretKey`, a valid `StripeWebhookSecret`, `GOOGLE_CLIENT_ID/SECRET`, `ADMIN_WHITELIST` containing your login email.
- [ ] `ln -sfn "$(pwd)" ~/projects/dolof` so MAMP serves `http://localhost:8888/dolof`.
- [ ] Stripe CLI running to forward webhooks to local:
      `stripe listen --forward-to localhost:8888/dolof/stripe-webhook`
      — copy the printed `whsec_...` into `.env` as `StripeWebhookSecret` and restart MAMP's PHP (or just re-request; `.env` is read per-request).
- [ ] `logs/` is writable (`app_log()` in `includes/logger.php`) — tail it during testing: `tail -f logs/*.log`.
- [ ] Confirm `ORDERING_START` / `ORDERING_END` in `.env` — for most of this plan, temporarily set `ORDERING_START` to a past time and `ORDERING_END` far in the future so checkout isn't blocked by the window (there's a dedicated section below for window behavior itself).
- [ ] Admin dashboard → **Online ordering** toggle set to OPEN.

Test data: `sql/seed_test_data.sql` and `scripts/seed-random-orders.php` can pre-populate orders (useful for admin/report/export testing without manually placing 20 orders).

---

## 1. Public order form (`order.php`) — rendering & client-side behavior

- [ ] Page loads at `/dolof/order` with no PHP warnings/notices in `logs/` or browser console.
- [ ] Campus list renders from `data/life-groups.json`; selecting a campus populates the Lift Group type-ahead with that campus's groups only.
- [ ] Typing a Lift Group name not in the list is still accepted (free text).
- [ ] "I don't regularly attend Crosspoint" campus option selectable and widens correctly.
- [ ] Phone field live-formats as a US number while typing (digits only accepted).
- [ ] Font size +/- buttons change the form's font scale and persist across reload (localStorage).
- [ ] Add an attendee: first/last name required, box choice required per attendee; "child" checkbox moves them into the children count.
- [ ] Attendee row: Cancel discards the row; Edit re-opens a saved row with its data intact.
- [ ] Continue/submit button stays disabled until every required field across the form is filled (per commit `76cde99`).
- [ ] Whole lunch-box card is clickable to select/deselect (not just the radio hit-target).
- [ ] Box list shows "N left" only when remaining ≤ `DOLOS_LOW_STOCK_THRESHOLD` (30 by default); above that, no count shown.
- [ ] A box at 0 remaining renders sold-out/disabled and cannot be selected.
- [ ] Confirmation modal (before final submit) summarizes: attendees, box choices, totals — matches what was entered.
- [ ] Dish names with Chinese + English render with the English run visibly smaller/lighter (commit `2b1888b`).
- [ ] Test on iOS Safari specifically (regression fixed in `bd4d94b` — self-hosted Tailwind build, not the Play CDN) — page must not render blank.
- [ ] `remaining-counts.php` polling: open two browser tabs, submit an order in one, confirm the other tab's remaining counts update without a full reload.

## 2. Server-side validation (`create-checkout.php`)

Submit via the real form (not just curl) at least once per case so client-side validation is confirmed *not* to be the only gate — then optionally repeat with curl/Postman bypassing JS to confirm server-side enforcement:

- [ ] Missing first name / last name → rejected with field-specific error, form re-renders with previously entered values preserved (`$old`).
- [ ] Both email and phone blank → "Please provide either a valid email or a phone number."
- [ ] Malformed email (`foo@`) with blank phone → rejected.
- [ ] Valid phone only, no email → accepted.
- [ ] No campus selected → rejected.
- [ ] Lift Group blank → rejected; Lift Group > 20 chars → rejected.
- [ ] Zero attendees added → "Please add at least one person attending."
- [ ] 51+ attendees → "Attendance cannot exceed 50 people." (add via repeated quick entries or craft a POST for this one — UI may not make it easy to reach 51).
- [ ] Attendees with only children, no adults → "At least one person who is not 12 or under is required."
- [ ] Attendee first/last name > 100 chars → rejected.
- [ ] Attendee with no lunch box choice (and "Not Ordering" not selected) → rejected with per-attendee message.
- [ ] CSRF: resubmit a stale/duplicate tab (old `csrf_token`) → 403 "Forbidden — invalid CSRF token."
- [ ] GET request to `/create-checkout` → redirects to `/order` (no POST body processed).
- [ ] Submitting when `campuses()` returns `[]` (temporarily empty `data/life-groups.json`) → "Campus options are not configured." (restore file after).

## 3. Free RSVP path (all attendees "Not Ordering")

- [ ] Every attendee set to "Not Ordering" / no box → order confirms immediately with **no Stripe redirect**, lands on `success.php?rsvp=1`.
- [ ] Order in DB: `status='paid'`, `payment_method='rsvp'`, `total_amount_cents=0`, `hold_expires_at` NULL.
- [ ] Confirmation email sent immediately (no webhook involved) — verify `confirmation_email_sent=1` and email received.
- [ ] Success page shows "RSVP confirmed!" (not "Order confirmed!") and "No lunch boxes ordered — attendance only."
- [ ] Reloading `/dolof/success?rsvp=1` directly (without the session flag) redirects to `/order` (session `rsvp_order_id` is unset after first read — can't replay).
- [ ] Mixed order (some attendees pick a box, others "Not Ordering") → goes through the **paid** path below, not RSVP, and only the box-selecting attendees count toward `$lines`.

## 4. Paid checkout — happy path

- [ ] Fill form with 1+ attendees each choosing a box, submit → redirected to Stripe Checkout with correct line items (box name + code, correct unit price, correct quantity/total).
- [ ] Pay with `4242 4242 4242 4242`, any future expiry, any CVC → redirected to `/success?session_id=...`.
- [ ] Success page shows order id, name/phone, campus/lift group, attendee names (adults/children lines), itemized boxes with qty + subtotal, correct total paid.
- [ ] DB: order `status='paid'`, `hold_expires_at` NULL, `stripe_session_id` set, `confirmation_email_sent=1`.
- [ ] Confirmation email received: correct items table, total, campus, lift group, attendee names, event title/date/location (as set in admin).
- [ ] Stripe CLI log shows `checkout.session.completed` delivered and `stripe-webhook.php` returned 200.
- [ ] (Optional async methods) `checkout.session.async_payment_succeeded` finalizes when paid; `async_payment_failed` releases the hold. Webhook ignores `completed` events that are still `payment_status=unpaid`.
- [ ] Reload the success page (same `session_id`) — no duplicate email sent, no error (idempotent `order_finalize_payment`).
- [ ] `remaining-counts.php` reflects the decreased remaining count for the purchased box(es) after payment.

## 5. Payment failure / decline / abandonment

- [ ] Use decline test card `4000 0000 0000 0002` → Stripe shows decline, user stays on Stripe page (can retry) — order stays `pending` with an active hold until retried or abandoned.
- [ ] Use `4000 0025 0000 3155` (3D Secure required) → complete the authentication challenge → payment succeeds → same happy-path assertions as §4.
- [ ] On Stripe Checkout, click "back"/close the tab → lands on `/cancel?order=<id>` → order `status='cancelled'`, `hold_expires_at` NULL, `logs/` shows "checkout cancelled by user" → redirected to `/order?cancelled=1` with the amber "Payment was not completed" banner.
- [ ] After cancel, re-submitting the same selections works normally (no stale hold blocking capacity).
- [ ] Let a Checkout session sit unpaid until Stripe fires `checkout.session.expired` (or trigger via Stripe CLI: `stripe trigger checkout.session.expired` with matching metadata) → webhook marks order `expired`, seat released, confirmed via `remaining-counts.php` increasing.

## 6. Capacity enforcement & concurrency

Set a box's `cap` low (e.g. 2) via the admin dashboard for this section, then restore it after.

- [ ] Order exactly up to the cap → succeeds.
- [ ] One more order for the same box after cap reached → rejected with "X is sold out." (or "Only N left of X" if a partial quantity request exceeds what remains).
- [ ] A `pending` (unexpired) hold counts against capacity: start checkout for the last available unit in one browser, without paying, then try to buy it again in a second browser/incognito session → second attempt rejected while the first hold is live.
- [ ] Cancel or let the first hold expire → the unit becomes purchasable again.
- [ ] **Race condition**: fire two near-simultaneous submissions for the last unit of a box (two terminal `curl` POSTs at once, or two browser tabs submitted within the same second) → exactly one succeeds, the other gets a capacity error. Never both succeed (verify via `GET_LOCK` in `create_pending_order()` — check DB directly: total paid+held quantity never exceeds `cap`).
- [ ] An admin lowering a box's `cap` below its current paid+held total does **not** retroactively cancel existing orders — new attempts against that box are rejected; existing paid orders remain `paid` (may show in admin as capacity-flagged if applicable via `order_overbook_reasons`).
- [ ] Deactivating a box (`active=0`) removes it from the public form and from `boxes_with_remaining()`; an attendee that already had it selected client-side and resubmits gets "An unavailable lunch box was selected."

## 7. Ordering window & admin open/close toggle

- [ ] `ORDERING_START` in the future → form is browsable (can fill it out) but banner reads "Ordering is not open yet" and checkout is blocked server-side even if a request is forced (`ordering_accepts_checkout()` false).
- [ ] Current time within `[ORDERING_START, ORDERING_END]` and admin toggle OPEN → green "Ordering is open" banner, checkout works, countdown to `ORDERING_END` displays and ticks down client-side.
- [ ] Current time past `ORDERING_END` → red "Ordering has closed" banner, checkout blocked with "Ordering has closed. The window ended ..." even if admin toggle is OPEN.
- [ ] Admin toggle set to CLOSED (independent of the window) → red "Ordering is temporarily closed" banner at any time, checkout blocked with "Online ordering is currently closed."
- [ ] Missing/blank `ORDERING_START` or `ORDERING_END` → treated as open-ended (no lower/upper bound) per `ordering_within_window()`.
- [ ] Malformed date string in either env var → `ordering_env_time()` returns null gracefully (no PHP exception), treated as unbounded on that side.

## 8. Admin — auth & dashboard

- [ ] `/admin` unauthenticated → shows "Sign in with Google," no dashboard data leaks.
- [ ] Any admin page hit directly while logged out (e.g. `/admin/dashboard`, `/admin/orders`, `/admin/export`) → redirected to `/admin` (`require_admin()`).
- [ ] Google sign-in with an email **not** in `ADMIN_WHITELIST` → redirected to `/admin?denied=1`, no session granted; `logs/` records "login denied."
- [ ] Google sign-in with an email in `ADMIN_WHITELIST` (comma-separated list, case-insensitive) → session granted, lands on `/admin/dashboard`.
- [ ] `email_verified=false` from Google (rare, but test if you can simulate) → denied even if the email matches the whitelist.
- [ ] Logout clears the session (`/admin/logout`) and subsequent admin page hits redirect to sign-in again.
- [ ] Dashboard totals (paid orders count, revenue, active holds) match what's actually in the DB.
- [ ] Toggle "Open/Close ordering" → setting persists (`dolos_settings` table), public form banner reflects it on next load.
- [ ] Toggle "Payment page" between on-site Elements and Stripe hosted page → `checkout_mode` persists; new paid checkouts follow the selected mode (in-flight sessions keep their original mode).
- [ ] Elements mode: Confirm & Pay → lands on `/pay` with order summary, Payment Element, Pay button, Cancel, and hold countdown; successful card payment reaches `/success` and finalizes the order.
- [ ] Hosted (kill switch) mode: Confirm & Pay → redirects to Stripe Checkout hosted page as before.
- [ ] Edit a box's name/price/cap/active and Save → persists; price stored correctly in cents (`$12.50` → `1250`); `cap=0` or blank treated as 0 (`max(0, ...)`); unchecking Active removes it from the public form.
- [ ] Submitting the box-edit form with a blank name for one row → that row is silently skipped (per `if ($id > 0 && $name !== '')`), others still save — verify this is the intended behavior, not silently losing data.
- [ ] Save event details (title/date/location) → persists and appears correctly in the next confirmation email sent.
- [ ] All admin POST actions (`toggle_ordering`, `toggle_checkout_mode`, `save_event`, `save_boxes`) reject a missing/invalid CSRF token with 403.

## 9. Admin — orders, order detail, reports, export

- [ ] `/admin/orders` default filter is `status=paid`; switching to pending/expired/cancelled/all filters correctly.
- [ ] Filtering by box code shows only orders containing that box.
- [ ] An invalid `status` query param falls back to `paid` (not an SQL error).
- [ ] `items_summary` column (`CODE×qty, CODE×qty`) matches the order's actual line items.
- [ ] `/admin/order-view?id=<id>` shows full detail for a single order (attendee names, items, payment status) — test with a paid, pending, and expired order.
- [ ] `/admin/report` and `/admin/report-group` show aggregate counts (by box / by campus or life group, whatever they group by) that match manual totals from the orders list.
- [ ] `/admin/export` downloads a CSV with UTF-8 BOM (opens correctly in Excel, no mojibake on Chinese box names), one row per **paid** order only, one quantity column per box code, correct totals, flagged/flag-reason columns populated for any capacity-flagged order.
- [ ] Export with zero paid orders → still downloads a valid CSV with header row only, no PHP error.

## 10. Cron / reconciliation (`scripts/cleanup-expired-orders.php`)

- [ ] Create a pending order, let its hold lapse (or set `hold_expires_at` to the past directly in DB for speed), then run `php scripts/cleanup-expired-orders.php` from the CLI.
- [ ] If the Stripe session was actually paid (simulate: pay it, but suppress/skip the webhook so it's still `pending` in DB) → cron recovers it: finalizes to `paid`, sends the confirmation email exactly once, logs "recovered paid order."
- [ ] If the Stripe session was never paid → cron marks it `expired`, logs "order expired," and the box's remaining count increases.
- [ ] If Stripe's API call fails/times out for a given order → that order is skipped (counted, logged), not incorrectly expired.
- [ ] Script prints a one-line summary when run from CLI (`Cleanup complete: recovered=N, expired=N, skipped=N`) and logs the same to `logs/`.
- [ ] Confirm the cron entry from the README actually runs on schedule once deployed: `*/5 * * * * php /path/to/dolof/scripts/cleanup-expired-orders.php >> /path/to/dolof/logs/cron.log 2>&1`.

## 11. Security / hardening spot-checks

- [ ] `.htaccess` blocks direct access to `.env`, `includes/`, `sql/`, `scripts/`, `logs/` — hit each URL directly in a browser and confirm 403/404, not file contents.
- [ ] Stripe webhook endpoint rejects a request with a missing/bad `Stripe-Signature` header (400 "Invalid signature") — try `curl -X POST` with no signature.
- [ ] Stripe webhook endpoint with `StripeWebhookSecret` unset → 500 "Webhook secret not configured" rather than silently accepting unsigned events.
- [ ] All user-supplied strings shown back (name, campus, lift group, attendee names) are HTML-escaped in the confirmation page, admin views, and email — try a name like `<script>alert(1)</script>` and confirm it renders as literal text everywhere, including the email.
- [ ] SQL injection spot-check: submit `campus`/`lift_group`/name fields containing `' OR '1'='1` — should be treated as literal text (all queries are parameterized `PDO::prepare`), no error, no data leak.
- [ ] Session cookie / CSRF token behaves correctly over HTTP locally and (on production) is exercised over HTTPS.

## 12. Production go-live checklist (from README, before flipping `ORDERING_START` live)

- [ ] `composer install --no-dev --optimize-autoloader` run locally; `vendor/` uploaded to the host (gitignored, not in the repo).
- [ ] `sql/production.sql` run against `crossp11_db1`; any files in `sql/migrations/` applied if upgrading an existing DB.
- [ ] Production `.env`: correct DB block, `APP_ENV=production`, `APP_URL=https://crosspointchurchsv.org/dolof`, **live** Stripe keys, correct `ADMIN_WHITELIST`.
- [ ] Stripe Dashboard (live mode): webhook endpoint `https://crosspointchurchsv.org/dolof/stripe-webhook` registered for `checkout.session.completed`, `checkout.session.expired`, `checkout.session.async_payment_succeeded`, and `checkout.session.async_payment_failed`; signing secret copied into production `.env`.
- [ ] Google Cloud Console: production redirect URI `https://crosspointchurchsv.org/dolof/admin/google-callback` added to the OAuth client.
- [ ] Cron installed on the host for `cleanup-expired-orders.php`.
- [ ] `logs/` writable on the host and confirmed not publicly served.
- [ ] Run the **full happy path** (§4) once against production with a real low-value live card (or Stripe's live-mode test tools if available) before announcing the form publicly — confirm the actual confirmation email arrives from the real mailer (`mail()`), not just locally.
- [ ] Confirm `ORDERING_START` / `ORDERING_END` on production `.env` match the intended go-live window before the form is shared out.

---

## 13. Stress test — one registrant, 40 attendees on a single order

A Lift Group leader or parent filling out the form once on behalf of ~40
people is a realistic and expected use case here (this is a group lunch
order, not a per-person checkout). The relevant code paths: `posted_form_attendees(50)`
caps at 50 rows (`includes/helpers.php:201`), all 40 people's box choices get
aggregated into **at most 5** Stripe line items (one per box code, since
quantity is summed in `create-checkout.php:139-144`), and `create_pending_order()`
takes a `GET_LOCK` per distinct box code touched — not per attendee — so a
40-person order only ever locks up to 5 box locks, in code order.

This section validates that a single large order behaves correctly end to
end, doesn't silently drop data, and doesn't break the capacity guard.

### 13.1 Setup

- [ ] Reset box caps to a default (e.g. 100) so this order doesn't collide with capacity limits — capacity interaction is tested separately in 13.4.
- [ ] Prepare a name list of 40 people in advance (a spreadsheet or text file) so data entry is fast and repeatable across runs — mix in: a few names with apostrophes/hyphens (`O'Brien`, `Mary-Jane`), a couple of Chinese names, one name at the 100-char maxlength boundary, and duplicate first+last name pairs (two "John Smith"s) to confirm the app doesn't dedupe people.
- [ ] Distribute the 40 across boxes so all 5 codes (A–E) get used and quantities are uneven (e.g. A:12, B:10, C:8, D:6, E:4) — this exercises the per-box aggregation and multi-lock path more than an even split would.
- [ ] Include at least 8 children (checkbox) spread across multiple adults, and confirm at least 1 adult is present (children-only is rejected — see §2).

### 13.2 Functional correctness at 40-attendee scale

- [ ] Add all 40 attendees through the real UI (not a crafted POST) — confirm the attendee list UI stays responsive (no visible lag/freeze) as rows accumulate; scrolling through 40 collapsed rows is usable.
- [ ] Confirmation modal before submit correctly summarizes all 40 people and the aggregated per-box totals (e.g. "Box A ×12, Box B ×10, ..."), not just the first page/screen of them.
- [ ] Submit → Stripe Checkout line items show **exactly 5 line items max** (one per box code actually used), each with the correct summed quantity and unit price — not 40 individual line items.
- [ ] Checkout total = sum of (box price × aggregated qty) across all 5 lines — hand-verify the arithmetic against your prepared list.
- [ ] Pay with `4242 4242 4242 4242` → order finalizes; DB row has `attending_adults` + `attending_children` = 40, `adult_names`/`child_names` JSON contains all 40 people with correct first/last/box/child split (spot-check the two duplicate "John Smith" entries both appear as separate array entries, not collapsed into one).
- [ ] Success page renders all 40 names under Adults/Children lines without truncation, and the itemized box table shows the correct aggregated qty/subtotal per box (still ≤5 rows).
- [ ] Confirmation email: items table has the same ≤5 aggregated rows; body doesn't choke on 40 names in `{{ADULT_NAMES_LINE}}` / `{{CHILD_NAMES_LINE}}` (check for a reasonable line length / wrapping in an actual email client, not just raw HTML).
- [ ] Admin → Orders: `items_summary` column (`A×12, B×10, C×8, D×6, E×4`) matches; `/admin/order-view` shows the full 40-name roster without pagination cutting names off.
- [ ] Admin → Export CSV: the order's row has correct per-box quantity columns (summing to 40 across Adults+Children columns as applicable) and the Adult Names / Child Names cells contain all names, semicolon-separated, still parseable as one CSV field when opened in Excel/Sheets (no stray unescaped commas/quotes breaking column alignment — apostrophe names like `O'Brien` are a good check here).

### 13.3 Boundary & abuse cases around the 40–50 range

- [ ] Exactly 50 attendees (the documented max) → succeeds.
- [ ] 51 attendees added through the UI, if the UI allows it → rejected server-side with "Attendance cannot exceed 50 people." and the form re-renders with all 51 entries preserved in `$old` (confirm none silently vanish from the re-rendered form).
- [ ] **Crafted POST with 55 `attendee_first[]`/`attendee_last[]` entries** (bypass the UI — e.g. curl or browser devtools editing the form before submit): confirm `posted_form_attendees(50)` truncates silently to the first 50 rather than erroring. This is expected code behavior, not a bug, but verify the *specific* 50 that get kept are rows 0–49 in submission order, and that whoever operates the form has no way to know 5 people were silently dropped — flag this to the site owner as a known gap if you agree the UI should instead warn/reject above 50 rather than truncate silently.
- [ ] A single box's requested quantity within the 40-person order exceeding `DOLOS_MAX_QTY_PER_BOX` (10 by default) — note `create-checkout.php` aggregates attendee box picks with **no per-line cap check against `DOLOS_MAX_QTY_PER_BOX`** (that constant is only enforced by the old qty-stepper UI, not the attendee-grid flow with unlimited people per box). Confirm this explicitly: submit 12 people all choosing Box A and verify whether the order succeeds with quantity 12 (exceeding the "1–10 per box" figure in the README) or is rejected — this tells you whether the README's per-box qty cap description is stale for the current attendee-based form.

### 13.4 Interaction with capacity limits

- [ ] Set Box A's `cap` to 30 (already has some paid orders from earlier testing, or start fresh) so that fewer than 40 units remain available.
- [ ] Submit the 40-person order where 12 people chose Box A, with only e.g. 8 remaining → confirm the **entire order is rejected** (not partially accepted) with "Only 8 left of Lunch Box A." and no `pending` order or partial hold is left behind — check the DB directly (`SELECT * FROM dolos_orders WHERE email = ...`) to confirm no half-committed row exists (this is the transaction/rollback path in `create_pending_order()`).
- [ ] Re-run with Box A's remaining capacity ≥12 → succeeds, and confirm the hold/paid quantity that gets recorded is exactly 12, not 40 (i.e., aggregation-by-code is correct, not accidentally charging the box's full attendee count).
- [ ] With two different boxes both near their cap (e.g. Box A has 10 left, Box D has 3 left, and the order requests 12×A / 6×D) → confirm the order is rejected citing **both** shortfalls in the error list (the loop in `create_pending_order()` collects all box errors before rejecting, not just the first one found), and that acquiring/releasing the multiple `GET_LOCK`s doesn't deadlock or leave a lock held (`SELECT IS_FREE_LOCK('dolos_box:A')`, `...('dolos_box:D')` should both return 1 immediately after the rejected attempt).
- [ ] Concurrency: with two different large orders (e.g. one registrant submitting 40 people while another registrant simultaneously submits a smaller order touching an overlapping box code near its cap) submitted within the same second → confirm the pending capacity re-check under lock still enforces the cap correctly across both, same as the two-terminal-curl test in §6, just with a multi-line order on one side instead of a single-line order.

### 13.5 Resource/perf sanity (not expected to be an issue at this scale, but confirm)

- [ ] PHP `max_input_vars` isn't hit: a 40-person submission posts roughly 40×2 (first/last arrays) + 40 (box) + up to 40 (child checkboxes) + ~7 top-level fields ≈ 170 POST fields — comfortably under PHP's default 1000, but confirm no "input variables exceeded" warning appears in `logs/` or the PHP error log.
- [ ] Page load time for `order.php` and the success page stays reasonable (sub-second locally) with 40 attendees rendered — no noticeable degradation vs. a 2-person order.
- [ ] `create_pending_order()`'s transaction (insert order + up to 40 `dolos_order_items` rows... actually confirm: does the app insert one item row per *box line* [≤5] or one per *attendee* [40]? Check `sql/dev_bootstrap.sql`'s `dolos_order_items` schema and the `$itemStmt` loop in `includes/orders.php:104-118` — it loops over `$lines`, i.e. the aggregated ≤5 rows, not per-attendee) commits without a noticeable delay.

---

## Notes on scope

This plan is manual/exploratory because the codebase has no automated test
harness. If recurring regressions become a problem, the highest-value places
to add automated coverage would be: `create_pending_order()` concurrency
(§6 and §13.4, scriptable with a small PHP/bash harness spawning parallel
curl requests) and the server-side validation rules in `create-checkout.php`
(§2 and §13.3, easy to hit directly with curl + assertions on the
response/DB state).
