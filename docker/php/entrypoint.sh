#!/bin/sh
set -e

mkdir -p /app/runtime/cache /app/runtime/logs /app/web/assets

chmod -R 775 /app/runtime /app/web/assets /app/storage || true

exec "$@"