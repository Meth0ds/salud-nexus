#!/bin/sh
set -eu

load_secret() {
    variable_name="$1"
    secret_file="$2"

    if [ ! -r "$secret_file" ]; then
        echo "Required secret file for ${variable_name} is not readable." >&2
        exit 1
    fi

    secret_value="$(cat "$secret_file")"

    if [ -z "$secret_value" ]; then
        echo "Required secret ${variable_name} is empty." >&2
        exit 1
    fi

    export "${variable_name}=${secret_value}"
}

if [ -n "${APP_KEY_FILE:-}" ]; then
    load_secret APP_KEY "$APP_KEY_FILE"
fi

if [ -n "${AUDIT_INTEGRITY_KEY_FILE:-}" ]; then
    load_secret AUDIT_INTEGRITY_KEY "$AUDIT_INTEGRITY_KEY_FILE"
fi

if [ -n "${DB_PASSWORD_FILE:-}" ]; then
    load_secret DB_PASSWORD "$DB_PASSWORD_FILE"
fi

if [ -n "${REDIS_PASSWORD_FILE:-}" ]; then
    load_secret REDIS_PASSWORD "$REDIS_PASSWORD_FILE"
fi

: "${APP_KEY:?APP_KEY or APP_KEY_FILE must be configured}"
: "${AUDIT_INTEGRITY_KEY:?AUDIT_INTEGRITY_KEY or AUDIT_INTEGRITY_KEY_FILE must be configured}"
: "${DB_PASSWORD:?DB_PASSWORD or DB_PASSWORD_FILE must be configured}"
: "${REDIS_PASSWORD:?REDIS_PASSWORD or REDIS_PASSWORD_FILE must be configured}"

mkdir -p \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

# This is the only approved Composer lifecycle equivalent in the runtime image.
php artisan package:discover --ansi --no-interaction >/dev/null

exec "$@"
