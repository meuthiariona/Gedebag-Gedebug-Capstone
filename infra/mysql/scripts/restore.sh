#!/bin/bash
# Pulihkan SELURUH database dari file backup infrastruktur (disaster recovery).
#   docker compose exec mysql bash /scripts/restore.sh gie-db-20261111-010000.sql.gz
#
# Berbeda dengan restore di halaman admin (per data), ini memulihkan database utuh,
# termasuk struktur tabel, trigger, dan audit log, misalnya saat database rusak total.
set -euo pipefail

FILE="${1:-}"
if [ -z "$FILE" ]; then
    echo "Pemakaian: restore.sh <nama-file-backup>"
    echo "Backup tersedia:"
    ls -1t /backups/gie-db-*.sql.gz 2>/dev/null | xargs -r -n1 basename | sed 's/^/   /'
    exit 1
fi

PATH_FILE="/backups/$(basename "$FILE")"
[ -f "$PATH_FILE" ] || { echo "File tidak ditemukan: $PATH_FILE"; exit 1; }

# 1) Cek integritas: checksum harus cocok dengan saat backup dibuat
if [ -f "$PATH_FILE.sha256" ]; then
    (cd /backups && sha256sum -c "$(basename "$PATH_FILE").sha256" >/dev/null) \
        || { echo "GAGAL: checksum tidak cocok, file rusak atau sudah diubah. Restore dibatalkan."; exit 1; }
    echo ">> Checksum cocok."
else
    echo "PERINGATAN: file .sha256 tidak ada, integritas tidak bisa dicek."
fi
gzip -t "$PATH_FILE" || { echo "GAGAL: arsip gzip rusak."; exit 1; }

# 2) Konfirmasi
if [ "${2:-}" != "--force" ]; then
    read -r -p "Database '${DB_DATABASE}' akan DIGANTI dengan isi $(basename "$PATH_FILE"). Ketik RESTORE: " jawab
    [ "$jawab" = "RESTORE" ] || { echo "Dibatalkan."; exit 1; }
fi

# 3) Pulihkan
START=$(date +%s)
gunzip -c "$PATH_FILE" | mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" "${DB_DATABASE}"
END=$(date +%s)

echo ">> Restore selesai dalam $((END - START)) detik (catat sebagai RTO aktual)."
