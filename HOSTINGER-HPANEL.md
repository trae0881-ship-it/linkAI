# Hostinger hPanel — no-SSH deploy guide

Shared/Business hPanel hosting has no Redis and no long-running process, so this app runs
in "database queue" mode: jobs and the scheduler are driven by hPanel Cron Jobs instead of
`horizon`/`queue:work` daemons or Docker. This guide assumes PHP 8.3 and MySQL, both
available in hPanel.

## 0) Before you upload
On your own computer (this sandbox cannot run Composer):
```
composer install --no-dev --optimize-autoloader
```
This creates a `vendor/` folder. Zip the WHOLE project (including `vendor/`) and upload
that — hPanel's File Manager can extract a zip for you.

## 1) Database
hPanel → **Databases → MySQL Databases** → create a database, a user, and a strong
password. Note the database name, username, password, and host (usually `localhost`).

## 2) Where the files go (important)
Laravel must NOT be placed directly inside `public_html` — that would expose `.env`,
`app/`, etc. to the internet. Correct layout:
```
/home/<you>/domains/yourdomain.com/            <- Laravel project root (app, bootstrap,
                                                    vendor, artisan, composer.json, ...)
/home/<you>/domains/yourdomain.com/public_html <- ONLY the contents of Laravel's public/
```
Steps in File Manager:
1. Go to `domains/yourdomain.com/`. Delete the default `public_html` folder.
2. Upload and extract this project's zip into `domains/yourdomain.com/` (so `app/`,
   `vendor/`, `public/`, etc. sit directly there).
3. Rename the extracted `public/` folder to `public_html`.
   (`domains/yourdomain.com/public_html/index.php` should now exist.)

## 3) .env
In `domains/yourdomain.com/`, copy `.env.example` to `.env` and fill it in:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_FRONTEND_URL=https://yourdomain.com
SANCTUM_STATEFUL_DOMAINS=yourdomain.com
# Nothing here is used until install.php sets APP_KEY for you.

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<from step 1>
DB_USERNAME=<from step 1>
DB_PASSWORD=<from step 1>

# No Redis on shared hosting - drive everything from the database + cron instead:
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

# No S3? Use local public storage instead (uploaded images will be served from /storage):
FILESYSTEM_DISK=public

ADMIN_NAME="Admin"
ADMIN_EMAIL=your-real-email@example.com
ADMIN_PASSWORD=<12+ characters, put it only here, never in chat>

LLM_PROVIDER_KEY=<your OpenAI key>
IMAGE_PROVIDER_KEY=<your OpenAI key>

RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
RAZORPAY_WEBHOOK_SECRET=

MAIL_MAILER=smtp
MAIL_HOST=<hPanel Emails -> your mailbox's SMTP host>
MAIL_USERNAME=<mailbox address>
MAIL_PASSWORD=<mailbox password>

INSTALL_TOKEN=<make up a long random string, e.g. 40 random letters/numbers>
```
`FILESYSTEM_DISK=public` needs no separate account, but S3-compatible storage (Cloudflare
R2 has a free tier) is more reliable at scale — switch to it later if you like.

## 4) Run the installer (no SSH needed)
Visit:
```
https://yourdomain.com/install.php?token=<the INSTALL_TOKEN you set>
```
This generates `APP_KEY`, runs migrations, creates the default site pages/legal pages and
your admin account, and caches config/routes/views. Read the plain-text output for errors.

**Immediately after it says DONE**, delete `public_html/install.php` in File Manager (or
remove `INSTALL_TOKEN` from `.env`) — leaving it reachable is a standing risk.

## 5) Cron Jobs (replaces the queue worker + scheduler)
hPanel → **Advanced → Cron Jobs** → add these two (edit the path for your username/domain):
```
* * * * * php /home/<you>/domains/yourdomain.com/artisan schedule:run >> /dev/null 2>&1
* * * * * php /home/<you>/domains/yourdomain.com/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```
Both run every minute. `schedule:run` triggers the metrics sync every 6 hours as coded;
`queue:work --stop-when-empty` processes pending jobs (URL analysis, media generation,
publishing) and exits, so it never becomes a stuck long-running process on shared hosting.
Background work (e.g. a submitted URL being analyzed) can take up to ~1 minute to start,
since it waits for the next cron tick.

## 6) Check it
- `https://yourdomain.com/` — public homepage.
- `https://yourdomain.com/app/` — sign in with ADMIN_EMAIL/ADMIN_PASSWORD, then edit pages,
  site details and (if you have keys) the Razorpay gateway from the admin sidebar.

## Notes / limits in this mode
- Meta (Facebook/Instagram) publishing still needs `META_CLIENT_ID/SECRET/REDIRECT_URI`
  from a Meta Developer app — unrelated to hosting, same as any environment.
- If your hPanel plan's PHP version isn't 8.3, set it: hPanel → **Advanced → PHP
  Configuration** → PHP version 8.3, and enable extensions: pdo_mysql, mbstring, intl,
  bcmath, zip, curl, fileinfo (usually already on).
- If `composer install` isn't available to you at all (no local PHP either), hPanel's
  **Advanced → SSH Access** (where available) lets you run it directly on the server
  instead of uploading `vendor/` yourself: `cd domains/yourdomain.com && composer install
  --no-dev --optimize-autoloader`.
