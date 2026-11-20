#!/bin/bash
# Pasang hardening database (trigger audit log). Jalankan setelah migrasi:
#   docker compose exec mysql sh /scripts/harden.sh
set -euo pipefail

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${DB_DATABASE}" < /scripts/hardening.sql
echo ">> Trigger append-only untuk activity_logs terpasang."

echo ">> Hak akses user database:"
for u in gie_app gie_migrator gie_backup; do
    mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -N -e "SHOW GRANTS FOR '${u}'@'%'" | sed 's/^/   /'
done
