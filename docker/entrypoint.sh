#!/bin/sh
set -e

PORT="${PORT:-8080}"
sed -i "s/__PORT__/${PORT}/" /etc/nginx/nginx.conf

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "FATAL: APP_KEY is not set. Generate one with 'php artisan key:generate --show' and set it in Railway's environment variables." >&2
    exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrations run on every deploy — safe because every migration in this
# project is additive/backward-compatible (see docs/migrations.md
# convention: never a destructive change in the same deploy that also
# ships code depending on the new shape).
php artisan migrate --force

# Creates the first admin from ADMIN_EMAIL / ADMIN_PASSWORD (Railway variables). Idempotent; never fatal.
php artisan db:seed --class=SiteContentSeeder --force || echo "WARN: site content seeding failed"
php artisan db:seed --class=AdminSeeder --force || echo "WARN: admin seeding skipped or failed - see message above"

exec "$@"
