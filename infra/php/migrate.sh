#!/bin/sh
# Persiapan aplikasi (dijalankan lewat: docker compose run --rm migrate)
# Memakai user database "gie_migrator" yang boleh membuat/mengubah tabel.
set -e
cd /var/www/html

[ -f .env ] || cp .env.example .env
composer install --no-interaction --prefer-dist --optimize-autoloader

# Cache konfigurasi lama (berisi user database aplikasi) dibuang dulu,
# supaya migrasi benar-benar memakai user gie_migrator.
php artisan config:clear >/dev/null || true
php artisan route:clear >/dev/null || true

# Buat APP_KEY jika belum ada
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

if [ "$SEED" = "1" ]; then
    echo ">> Membuat ulang database + data contoh (migrate:fresh --seed)"
    php artisan migrate:fresh --seed --force
else
    php artisan migrate --force
fi

[ -e public/storage ] || php artisan storage:link

echo ""
echo ">> Selesai. Langkah berikutnya: docker compose exec mysql bash /scripts/harden.sh"
