#!/bin/bash
# ============================================================================
#  Backup database otomatis (container "backup")
#
#  - Memakai user gie_backup yang hanya bisa MEMBACA database
#  - Hasil: infra/backups/gie-db-YYYYMMDD-HHMMSS.sql.gz + file .sha256 (cek integritas)
#  - Retensi: backup lebih tua dari BACKUP_KEEP_DAYS hari dihapus otomatis
#  - Backup pertama dibuat saat container menyala, lalu setiap BACKUP_INTERVAL_HOURS jam
#
#  Backup manual kapan saja:  docker compose exec backup bash /backup.sh now
# ============================================================================
set -euo pipefail

: "${DB_HOST:=mysql}"
: "${DB_DATABASE:=gie}"
: "${BACKUP_DIR:=/backups}"
: "${BACKUP_INTERVAL_HOURS:=24}"
: "${BACKUP_KEEP_DAYS:=7}"
# Opsi tambahan mysqldump (MySQL 8: jangan sertakan info GTID supaya mudah di-restore)
: "${MYSQLDUMP_EXTRA=--set-gtid-purged=OFF}"

export MYSQL_PWD="${BACKUP_DB_PASSWORD}"   # password lewat env, tidak terlihat di daftar proses

run_backup() {
    local ts file
    ts=$(date +%Y%m%d-%H%M%S)
    file="${BACKUP_DIR}/gie-db-${ts}.sql.gz"

    # --single-transaction: snapshot konsisten tanpa mengunci tabel (web tetap bisa dipakai)
    # shellcheck disable=SC2086
    mysqldump -h "${DB_HOST}" -u "${BACKUP_DB_USER}" \
        --single-transaction --triggers --no-tablespaces ${MYSQLDUMP_EXTRA} \
        "${DB_DATABASE}" | gzip -9 > "${file}.tmp"
    mv "${file}.tmp" "${file}"

    (cd "${BACKUP_DIR}" && sha256sum "$(basename "${file}")" > "$(basename "${file}").sha256")

    # Retensi
    find "${BACKUP_DIR}" -name 'gie-db-*.sql.gz*' -mtime +"${BACKUP_KEEP_DAYS}" -delete

    echo "[$(date '+%F %T')] Backup OK: $(basename "${file}") ($(du -h "${file}" | cut -f1))"
}

mkdir -p "${BACKUP_DIR}"

if [ "${1:-}" = "now" ]; then
    run_backup
    exit 0
fi

echo "Backup otomatis aktif: setiap ${BACKUP_INTERVAL_HOURS} jam, simpan ${BACKUP_KEEP_DAYS} hari."
while true; do
    run_backup || echo "[$(date '+%F %T')] BACKUP GAGAL" >&2
    sleep $((BACKUP_INTERVAL_HOURS * 3600))
done
