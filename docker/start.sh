#!/usr/bin/env sh
set -eu

database_path="${DB_DATABASE:-/tmp/irongym.sqlite}"

mkdir -p "$(dirname "$database_path")" storage/framework/cache storage/framework/sessions storage/framework/views
touch "$database_path"
export DB_DATABASE="$database_path"

# Render's manually-created service does not import .env.  Generate an
# instance key when APP_KEY was not configured so Laravel can encrypt cookies
# and sessions instead of returning HTTP 500 on the first request.
if [ -z "${APP_KEY:-}" ]; then
    export APP_KEY="$(php artisan key:generate --show)"
fi

php artisan migrate --force
php artisan db:seed --force

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
