#!/usr/bin/env sh
set -eu

mkdir -p "$(dirname "$DB_DATABASE")" storage/framework/cache storage/framework/sessions storage/framework/views
touch "$DB_DATABASE"

php artisan migrate --force
php artisan db:seed --force

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
