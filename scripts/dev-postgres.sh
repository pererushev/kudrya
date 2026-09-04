#!/usr/bin/env bash
set -euo pipefail

export PATH="/usr/lib/postgresql/16/bin:${PATH}"
PGDATA="${PGDATA:-/tmp/kudrya-pgdata}"
PORT="${PGPORT:-5433}"

if [[ ! -d "${PGDATA}" ]]; then
  initdb -D "${PGDATA}" --auth=trust --username=kudrya --no-instructions
  {
    echo "listen_addresses = '127.0.0.1'"
    echo "port = ${PORT}"
    echo "unix_socket_directories = '/tmp'"
  } >> "${PGDATA}/postgresql.conf"
fi

if ! pg_isready -h 127.0.0.1 -p "${PORT}" >/dev/null 2>&1; then
  pg_ctl -D "${PGDATA}" -l /tmp/kudrya-pg.log start
fi

createdb -h 127.0.0.1 -p "${PORT}" -U kudrya kudrya 2>/dev/null || true
createdb -h 127.0.0.1 -p "${PORT}" -U kudrya kudrya_test 2>/dev/null || true

echo "PostgreSQL ready on 127.0.0.1:${PORT} (user kudrya, db kudrya / kudrya_test)"
