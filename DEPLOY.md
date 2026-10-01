# Deploy guide

## Databases: PostgreSQL or MySQL (both supported)
Set `DB_CONNECTION=pgsql` (port 5432) or `DB_CONNECTION=mysql` (port 3306). Same migrations work on both.
Railway: add the PostgreSQL *or* MySQL plugin and set `DATABASE_URL=${{Postgres.DATABASE_URL}}` / `${{MySQL.MYSQL_URL}}`.

## Railway (recommended - full app, simplest path)
1. Push this folder to a private GitHub repo -> Railway -> New Project -> Deploy from GitHub repo (it builds the Dockerfile automatically).
2. Add a database plugin (Postgres or MySQL) and a Redis plugin.
3. Service -> Variables - set ALL of these before the first deploy, or the build will fail on a missing APP_KEY / fail its migration step:
   - APP_KEY = base64:<output of `openssl rand -base64 32`>, APP_ENV=production, APP_DEBUG=false
   - APP_URL=https://<your-railway-domain>, APP_FRONTEND_URL=<same>, SANCTUM_STATEFUL_DOMAINS=<domain without https://>
   - DB_CONNECTION=pgsql (or mysql), DATABASE_URL=${{Postgres.DATABASE_URL}} (or ${{MySQL.MYSQL_URL}})
   - REDIS_URL=${{Redis.REDIS_URL}} - keep QUEUE_CONNECTION/CACHE_STORE/SESSION_DRIVER on their default `redis` (Railway's Redis plugin makes this the easy path, unlike Hostinger's no-Redis mode)
   - Storage: S3-compatible (AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_BUCKET, AWS_ENDPOINT, AWS_DEFAULT_REGION) if you have a bucket, otherwise FILESYSTEM_DISK=public to start (files live on the container's own disk - fine initially, but not persisted across redeploys, so move to S3 once you have real users)
   - LLM_PROVIDER_KEY, IMAGE_PROVIDER_KEY (optional: TRENDS_PROVIDER=serpapi + key)
   - MAIL_* (needed for invite emails and password resets)
   - ADMIN_EMAIL, ADMIN_PASSWORD (12+ chars), ADMIN_NAME - this account is created automatically on first boot
   - RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET, RAZORPAY_WEBHOOK_SECRET (or leave blank and set these later from the admin panel's Payment gateway screen instead)
   - Do NOT set INSTALL_TOKEN - that's only for hosts with no SSH/entrypoint (Hostinger's install.php). On Railway, migrations and seeders already run automatically on every deploy (docker/entrypoint.sh), so leaving that installer reachable with a token set would be a pointless standing risk.
   - Do NOT set APP_PUBLIC_PATH - that's only for hosts where public/ was moved into a separately-named folder (Hostinger's public_html). Railway's Dockerfile keeps public/ exactly where Laravel expects it.
4. Settings -> Networking -> Generate Domain (health check path is already `/up`).
5. Open https://<domain>/app/ and sign in with ADMIN_EMAIL/ADMIN_PASSWORD. Default site pages (Home, About, Services, Contact, Privacy, Refund, Terms, Disclaimer) are seeded too - edit them from the admin sidebar's "Site admin" section before sharing the link publicly.
6. Optional custom domain (e.g. one bought on Hostinger): add it under Railway's domain settings, then create the CNAME record Railway shows you in Hostinger's DNS panel (hPanel -> Domains -> DNS Zone) - the app keeps running on Railway, you're only pointing the domain there, no need to also deploy the app itself to Hostinger.

## Razorpay setup
1. Razorpay Dashboard -> Account & Settings -> API Keys -> generate (start with Test mode keys).
2. Webhooks -> Add: URL `https://<domain>/api/v1/webhooks/razorpay`, secret = RAZORPAY_WEBHOOK_SECRET,
   events `payment.captured`, `order.paid`, `payment.failed`.
3. In the app: Billing -> Pay with Razorpay. Test with Razorpay's test card/UPI, then switch to Live keys.
4. Plan prices are placeholders: set BILLING_STARTER_PAISE / BILLING_PRO_PAISE (paise: Rs 499 = 49900) or edit config/billing.php.
   Paying activates/extends the workspace `plan` for 30 days; no features are gated by plan yet.
5. If the checkout popup is blocked, check the browser console for CSP errors (allowed origins live in SecurityHeaders).

## Hostinger
- VPS: `docker compose --profile postgres up -d --build` or `--profile mysql` (see docker-compose.yml; includes HTTPS via Caddy).
- Shared / Business hosting with MySQL (no Redis, no Docker) - "no-Redis mode". Confirm your plan gives PHP 8.3, SSH, Composer and cron:
  1. `composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix`
  2. .env: `DB_CONNECTION=mysql`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`, DB_* from hPanel, `APP_KEY` via `php artisan key:generate`
  3. `php artisan migrate --force && php artisan db:seed --class=AdminSeeder --force`
  4. Point the domain's document root at `public/`.
  5. Cron (every minute):
     `* * * * * cd /path/to/app && php artisan schedule:run >/dev/null 2>&1`
     `* * * * * cd /path/to/app && php artisan queue:work --stop-when-empty --max-time=55 >/dev/null 2>&1`
  6. Storage: S3-compatible is recommended; local disk works (`FILESYSTEM_DISK=local`) but Meta must be able to reach signed media URLs over HTTPS.
  Jobs run up to a minute late in this mode (cron granularity); video polling and scheduled posts inherit that delay.

## Public website + admin panel
- Public pages (server-rendered): `/` Home, `/about-us`, `/services`, `/contact-us`, `/privacy-policy`, `/refund-policy`, `/terms-and-conditions`, `/disclaimer`. The app is at `/app/`.
- First deploy seeds default content (only where missing - your edits are never overwritten).
- The user created from ADMIN_EMAIL / ADMIN_PASSWORD is the **platform admin**. After login the sidebar shows "Site admin":
  Website pages (edit text, publish/unpublish), Site details (name, email, phone, address, jurisdiction), Contact messages,
  Plans & pricing (add/edit/deactivate), Payment gateway (Razorpay keys, stored encrypted, write-only), All payments.
- Fill "Site details" first: the legal pages use {{business_name}}, {{contact_email}}, {{contact_phone}}, {{address}}, {{jurisdiction}}.
- The legal pages are generic TEMPLATES. Review them (ideally with a lawyer) and set your own refund window before going live.
- Razorpay keys entered in the admin panel override the RAZORPAY_* environment variables. Env vars are the more secure option
  (a database leak plus APP_KEY would expose panel-stored keys); the panel is provided for convenience.
- Contact messages are stored and shown in the admin panel; no email is sent unless you configure MAIL_* and add a notification.
