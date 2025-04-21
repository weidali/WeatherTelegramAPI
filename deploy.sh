#!/bin/bash

set -e

version=`/opt/php/8.2/bin/php artisan --version`

if [[ ${version} != *"Laravel Framework"* ]]; then
        echo "Not a Laravel app, exiting."
        exit;
fi

echo "Deploying..."

/opt/php/8.2/bin/php artisan down || true
git pull origin

/opt/php/8.2/bin/php /var/www/u1229058/data/bin/composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
/opt/php/8.2/bin/php artisan package:discover --ansi

/opt/php/8.2/bin/php artisan migrate --force
/opt/php/8.2/bin/php artisan cache:clear
/opt/php/8.2/bin/php artisan route:cache
/opt/php/8.2/bin/php artisan config:cache
/opt/php/8.2/bin/php artisan up

echo "Done!"
