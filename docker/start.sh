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

# Forward framework exceptions to Render's application log.
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"
# The sanitized schema intentionally has no sessions table.  File-backed
# sessions and cache work on Render's ephemeral free instances.
export SESSION_DRIVER="${SESSION_DRIVER:-file}"
export CACHE_STORE="${CACHE_STORE:-file}"
# Prevent mixed-content asset URLs when Render terminates HTTPS before the
# container. RENDER_EXTERNAL_URL is supplied by Render on deployed services.
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-https://irongym-fj53.onrender.com}}"

php artisan migrate --force
php artisan db:seed --force

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
