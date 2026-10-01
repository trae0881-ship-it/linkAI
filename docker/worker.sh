#!/bin/sh
# Redis queue -> Horizon; database queue (MySQL/Postgres without Redis) -> plain queue:work.
cd /var/www/html
if [ "${QUEUE_CONNECTION}" = "redis" ]; then
    exec php artisan horizon
else
    exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
fi
