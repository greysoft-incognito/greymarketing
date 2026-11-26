#!/usr/bin/env bash
#
# Activates an uploaded API release on the server.
#
# Usage: deploy.sh <base_path> <release>
#
# <base_path> holds:
#   releases/<release>/  the unpacked API code (vendor included)
#   shared/.env          the production environment file
#   shared/storage/      storage kept between releases
#   current              symlink to the live release (the web root is current/public)
#
# Environment:
#   PHP             PHP binary to run artisan with. Default: php
#   KEEP_RELEASES   How many releases to keep. Default: 5
#   RELOAD_COMMAND  Optional command run after the switch, e.g. "sudo systemctl reload php8.5-fpm"

set -euo pipefail

BASE_PATH="${1:?base path is required}"
RELEASE="${2:?release name is required}"
PHP="${PHP:-php}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
RELOAD_COMMAND="${RELOAD_COMMAND:-}"

RELEASE_PATH="$BASE_PATH/releases/$RELEASE"
SHARED_PATH="$BASE_PATH/shared"

if [ ! -d "$RELEASE_PATH" ]; then
    echo "Release $RELEASE_PATH does not exist." >&2
    exit 1
fi

if [ ! -f "$SHARED_PATH/.env" ]; then
    echo "Missing $SHARED_PATH/.env. Create it before the first deploy." >&2
    exit 1
fi

artisan() {
    "$PHP" "$RELEASE_PATH/artisan" "$@"
}

echo "Linking shared files"

mkdir -p \
    "$SHARED_PATH/storage/app/public" \
    "$SHARED_PATH/storage/framework/cache/data" \
    "$SHARED_PATH/storage/framework/sessions" \
    "$SHARED_PATH/storage/framework/views" \
    "$SHARED_PATH/storage/logs"

rm -rf "$RELEASE_PATH/storage"
ln -sfn "$SHARED_PATH/storage" "$RELEASE_PATH/storage"
ln -sfn "$SHARED_PATH/.env" "$RELEASE_PATH/.env"
mkdir -p "$RELEASE_PATH/bootstrap/cache"

echo "Running migrations"

artisan migrate --force --no-interaction

echo "Caching configuration, routes, events and views"

artisan optimize
artisan storage:link --force

echo "Switching current to $RELEASE"

ln -sfn "$RELEASE_PATH" "$BASE_PATH/current.next"
mv -Tf "$BASE_PATH/current.next" "$BASE_PATH/current"

if [ -n "$RELOAD_COMMAND" ]; then
    echo "Reloading PHP"
    eval "$RELOAD_COMMAND"
fi

echo "Restarting queue workers"

"$PHP" "$BASE_PATH/current/artisan" queue:restart

echo "Removing old releases"

cd "$BASE_PATH/releases"
ls -1t | tail -n +"$((KEEP_RELEASES + 1))" | while read -r old; do
    if [ "$old" != "$RELEASE" ]; then
        rm -rf -- "$old"
    fi
done

echo "Deployed $RELEASE"
