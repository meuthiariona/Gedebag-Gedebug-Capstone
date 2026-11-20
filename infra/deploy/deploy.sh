#!/usr/bin/env bash
# ============================================================================
#  Deploy / update aplikasi di server.
#
#  Server online (Azure, dll.), sertifikat Let's Encrypt:
#      bash infra/deploy/deploy.sh gie-capstone.southeastasia.cloudapp.azure.com email@kamu.com
#  VM lokal (VMware/VirtualBox), sertifikat self-signed:
#      bash infra/deploy/deploy.sh 192.168.xx.xx --self-signed
#  Update kode:
#      git pull && bash infra/deploy/deploy.sh
#
#  Yang dilakukan:
#   1. Membuat infra/.env berisi password database ACAK (sekali saja)
#   2. Meminta sertifikat HTTPS gratis dari Let's Encrypt, atau membuat sertifikat
#      self-signed untuk VM lokal (sekali saja)
#   3. Build & menyalakan semua container
#   4. Migrasi database (pertama kali + data contoh), lalu hardening database
#   5. Mengatur izin folder & cache konfigurasi Laravel
#   6. Memasang jadwal perpanjangan sertifikat otomatis
# ============================================================================
set -euo pipefail

INFRA="$(cd "$(dirname "$0")/.." && pwd)"
ROOT="$(cd "$INFRA/.." && pwd)"
cd "$INFRA"
C=(docker compose -f docker-compose.yml -f docker-compose.prod.yml)

# ---------- 1. infra/.env ----------
pw() { openssl rand -base64 30 | tr -dc 'A-Za-z0-9' | head -c 28; }

if [ ! -f .env ]; then
    DOMAIN_ARG="${1:-}"; EMAIL_ARG="${2:-}"
    if [ -z "$DOMAIN_ARG" ] || [ -z "$EMAIL_ARG" ]; then
        echo "Pemakaian pertama kali:"
        echo "  Server online : bash infra/deploy/deploy.sh <domain> <email>"
        echo "  VM lokal      : bash infra/deploy/deploy.sh <ip-vm> --self-signed"
        exit 1
    fi
    if [ "$EMAIL_ARG" = "--self-signed" ]; then CERT_MODE=self-signed; EMAIL_ARG=""; else CERT_MODE=letsencrypt; fi
    umask 077
    cat > .env <<ENV
# Dibuat otomatis oleh deploy.sh. JANGAN di-commit. Simpan salinannya di tempat aman.
DOMAIN=${DOMAIN_ARG}
LETSENCRYPT_EMAIL=${EMAIL_ARG}
CERT_MODE=${CERT_MODE}
DB_DATABASE=gie
MYSQL_ROOT_PASSWORD=$(pw)
APP_DB_PASSWORD=$(pw)
MIGRATOR_DB_PASSWORD=$(pw)
BACKUP_DB_PASSWORD=$(pw)
BACKUP_INTERVAL_HOURS=24
BACKUP_KEEP_DAYS=7
ENV
    umask 022
    echo ">> infra/.env dibuat dengan password acak."
fi
set -a; . ./.env; set +a

# ---------- 2. Sertifikat HTTPS ----------
mkdir -p certbot/conf certbot/www
CERT_MODE="${CERT_MODE:-letsencrypt}"
if [ ! -f certbot/conf/live/gie/fullchain.pem ]; then
    if [ "$CERT_MODE" = "self-signed" ]; then
        # VM lokal tidak bisa dijangkau Let's Encrypt, jadi sertifikat dibuat sendiri.
        # Browser akan memberi peringatan "Not secure"; untuk VM lokal itu wajar.
        echo ">> Membuat sertifikat self-signed untuk ${DOMAIN}"
        if [[ "$DOMAIN" =~ ^[0-9]+(\.[0-9]+){3}$ ]]; then SAN="IP:${DOMAIN}"; else SAN="DNS:${DOMAIN}"; fi
        mkdir -p certbot/conf/live/gie
        openssl req -x509 -newkey rsa:2048 -nodes -days 825 -sha256 \
            -subj "/CN=${DOMAIN}/O=Gie Entertainment (lokal)" -addext "subjectAltName=${SAN}" \
            -keyout certbot/conf/live/gie/privkey.pem -out certbot/conf/live/gie/fullchain.pem 2>/dev/null
        chmod 600 certbot/conf/live/gie/privkey.pem
    else
        echo ">> Meminta sertifikat HTTPS untuk ${DOMAIN}"
        "${C[@]}" stop nginx >/dev/null 2>&1 || true
        "${C[@]}" run --rm -p 80:80 certbot certonly --standalone \
            -d "${DOMAIN}" --cert-name gie \
            --email "${LETSENCRYPT_EMAIL}" --agree-tos --no-eff-email -n
    fi
fi

# ---------- 3. Container ----------
echo ">> Build & menyalakan container"
"${C[@]}" up -d --build --remove-orphans

# ---------- 4. Migrasi + hardening database ----------
FIRST=0
if [ ! -f .deployed ]; then
    FIRST=1
    echo ">> Deploy pertama: membuat database + data contoh"
    "${C[@]}" run --rm -e SEED=1 migrate
else
    "${C[@]}" run --rm migrate
fi
"${C[@]}" exec -T mysql bash /scripts/harden.sh

# ---------- 5. Izin folder & cache ----------
# Proses web (www-data, uid 82 di image Alpine) harus bisa menulis log, cache, dan upload.
sudo chown -R 82:82 "$ROOT/storage" "$ROOT/bootstrap/cache"
"${C[@]}" exec -T -u www-data app php artisan optimize
"${C[@]}" restart scheduler >/dev/null

# ---------- Password awal ----------
# Data contoh memakai password "password". Di internet itu berbahaya: orang lain bisa login
# lebih dulu dan mengganti password-nya. Jadi setiap akun diberi password acak,
# dan akun staf tetap wajib ganti password saat login pertama.
if [ "$FIRST" = "1" ]; then
    umask 077
    "${C[@]}" exec -T -u www-data -e HOME=/tmp app php artisan tinker --execute='
        foreach (App\Models\User::all() as $u) {
            $p = Illuminate\Support\Str::password(16, true, true, false);
            $u->forceFill(["password" => $p, "must_change_password" => $u->isAdmin()])->save();
            echo str_pad($u->email, 28)." ".$p.PHP_EOL;
        }' > .credentials-awal
    umask 022
fi

# ---------- 6. Perpanjangan sertifikat otomatis (setiap Senin 03:00) ----------
CRON=/etc/cron.d/gie-certbot
if [ "$CERT_MODE" = "letsencrypt" ] && [ ! -f "$CRON" ]; then
    sudo tee "$CRON" >/dev/null <<CRONTAB
SHELL=/bin/bash
0 3 * * 1 root cd ${INFRA} && ${C[*]} run --rm certbot renew --webroot -w /var/www/certbot -q && ${C[*]} exec -T nginx nginx -s reload
CRONTAB
fi

touch .deployed
echo ""
echo ">> Selesai: https://${DOMAIN}"
if [ -f .credentials-awal ]; then
    echo "   Password awal setiap akun (simpan, lalu hapus file infra/.credentials-awal):"
    sed 's/^/     /' .credentials-awal
fi
