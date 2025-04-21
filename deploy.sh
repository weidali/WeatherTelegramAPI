#!/bin/bash

set -e

version=`php82 artisan --version`

if [[ ${version} != *"Laravel Framework"* ]]; then
        echo "Not a Laravel app, exiting."
        exit;
fi

echo "Deploying..."

php82 artisan down || true
git pull origin

/opt/php/8.2/bin/php /var/www/u1229058/data/bin/composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
php82 artisan package:discover --ansi

php82 artisan migrate --force
php82 artisan cache:clear
php82 artisan route:cache
php82 artisan config:cache
php82 artisan view:cache
php82 artisan up

echo "Done!"
