DEPLOY A PRODUCCION: ver docs/deploy.md (checklist: migrate, permissions:sync, npm run build, caches)

CRON JOB: 

php artisan app:cleanup-expired-reservations

EN HOSTINGER:
/usr/bin/php /home/u791829980/domains/expressosofiaturismo.com.ar/public_html/panel/artisan app:cleanup-expired-reservations

/usr/bin/php /home/u791829980/domains/expressosofiaturismo.com.ar/public_html/panel/artisan app:cleanup-expired-reservations >> /dev/null 2>&1