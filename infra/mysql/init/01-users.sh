#!/bin/bash
# Dijalankan otomatis oleh image MySQL saat database PERTAMA KALI dibuat.
# Prinsip least privilege: tiap kebutuhan punya user database sendiri dengan hak seminimal mungkin.
set -euo pipefail

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
-- 1) Aplikasi sehari-hari: hanya baca/tulis DATA. Tidak bisa DROP, ALTER, CREATE, TRUNCATE, atau mengubah trigger.
CREATE USER IF NOT EXISTS 'gie_app'@'%' IDENTIFIED BY '${APP_DB_PASSWORD}';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`${DB_DATABASE}\`.* TO 'gie_app'@'%';

-- 2) Migrasi: boleh mengubah struktur tabel. Hanya dipakai saat setup/update, bukan oleh web.
CREATE USER IF NOT EXISTS 'gie_migrator'@'%' IDENTIFIED BY '${MIGRATOR_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO 'gie_migrator'@'%';

-- 3) Backup: hanya bisa MEMBACA. Kalau password ini bocor, data tetap tidak bisa diubah.
CREATE USER IF NOT EXISTS 'gie_backup'@'%' IDENTIFIED BY '${BACKUP_DB_PASSWORD}';
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON \`${DB_DATABASE}\`.* TO 'gie_backup'@'%';

FLUSH PRIVILEGES;
SQL

echo ">> User database gie_app, gie_migrator, gie_backup dibuat."
