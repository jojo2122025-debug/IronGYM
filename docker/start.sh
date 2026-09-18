#!/usr/bin/env sh
set -eu

database_path="${DB_DATABASE:-/tmp/irongym.sqlite}"

mkdir -p "$(dirname "$database_path")" storage/framework/cache storage/framework/sessions storage/framework/views
touch "$database_path"
export DB_DATABASE="$database_path"

php artisan migrate --force
php artisan db:seed --force

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
