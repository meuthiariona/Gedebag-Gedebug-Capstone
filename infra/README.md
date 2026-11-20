# Infrastruktur GIE Entertainment — kontrol keamanan di luar kode

> Mau deploy? **[DEPLOY.md](DEPLOY.md)**: online gratis di Azure for Students (HTTPS resmi). **[DEPLOY-VMWARE.md](DEPLOY-VMWARE.md)**: Ubuntu Server di VMware laptop sendiri.

Folder ini menjalankan aplikasi di atas infrastruktur yang dikeraskan (*hardened*) memakai Docker.
Kontrol di sini **melengkapi** kontrol yang sudah ada di kode Laravel (defense in depth), bukan menggantikannya.

```
 browser ──► nginx :8080 ──► app (PHP-FPM + Laravel) ──► mysql
             │                                        ▲
             │ header keamanan, rate limit login,     │ user DB hak minimal,
             │ blokir file sensitif                   │ trigger audit log append-only
                                         backup ───────┘ mysqldump terjadwal (user hanya-baca)
```

| Kontrol | Di kode Laravel | Di infrastruktur (folder ini) |
|---|---|---|
| Header keamanan | Middleware `SecurityHeaders` | Nginx `add_header` (berlaku juga untuk file statis & halaman error) |
| Batas percobaan login | Lockout per email + IP | Nginx `limit_req`: >10 POST/menit per IP → **429** sebelum sampai ke Laravel |
| Sembunyikan versi | Hapus `X-Powered-By` | `expose_php=Off`, `server_tokens off` |
| File sensitif | — | `.env`, `.git`, `.htaccess`, `*.sql`, `*.zip` → 404; file `.php` selain `index.php` tidak dieksekusi |
| Hak akses data | RBAC per peran | User MySQL terpisah: `gie_app` tanpa DROP/ALTER/TRUNCATE, `gie_backup` hanya-baca |
| Audit log append-only | Model menolak update/delete | **Trigger MySQL**: UPDATE/DELETE di `activity_logs` ditolak, termasuk dari phpMyAdmin/query langsung |
| Backup | Halaman Backup (Owner) | Container `backup` terpisah: `mysqldump` otomatis + checksum + retensi 7 hari |
| Eksekusi perintah OS | — | PHP-FPM: `exec`, `system`, `shell_exec`, `proc_open` dimatikan untuk proses web |

## Kebutuhan

- **Docker Desktop** (Windows/Mac) — https://www.docker.com/products/docker-desktop
- Tidak perlu XAMPP. Jika XAMPP sedang menyala, matikan MySQL-nya supaya tidak bentrok.

## Menjalankan (pertama kali)

Semua perintah dijalankan dari folder `infra/` (PowerShell, CMD, atau Git Bash sama saja).

```bash
cd infra

# 1. Buat file konfigurasi lalu GANTI semua password di dalamnya
copy .env.example .env          # Git Bash/Mac/Linux: cp .env.example .env

# 2. Nyalakan semua container (pertama kali agak lama karena mengunduh image)
docker compose up -d --build

# 3. Siapkan aplikasi: composer install, APP_KEY, migrasi + data contoh
docker compose run --rm -e SEED=1 migrate

# 4. Pasang hardening database (trigger audit log) + tampilkan hak akses tiap user
docker compose exec mysql bash /scripts/harden.sh
```

Buka **http://localhost:8080**. Akun demo sama seperti sebelumnya (`admin@nexa.test` / `password`, lalu wajib ganti password).

> Penting: setiap kali menjalankan `migrate` dengan `SEED=1` (database dibuat ulang), jalankan lagi langkah 4.

## Perintah sehari-hari

```bash
docker compose up -d                 # nyalakan
docker compose down                  # matikan (data tetap aman di volume)
docker compose logs -f nginx         # lihat log
docker compose exec backup bash /backup.sh now            # backup manual
docker compose exec mysql bash /scripts/restore.sh        # lihat daftar backup
docker compose exec mysql bash /scripts/restore.sh gie-db-XXXXXXXX-XXXXXX.sql.gz   # restore
```

Hasil backup ada di `infra/backups/` dan log Nginx di `infra/logs/nginx/`, keduanya di luar container.

## Membuktikan setiap kontrol (untuk laporan)

Ambil screenshot output perintah berikut.

**1. Header keamanan & versi tersembunyi**
```bash
curl -I http://localhost:8080/login
curl -I http://localhost:8080/robots.txt
```
Harus ada `X-Frame-Options`, `Content-Security-Policy`, dan lainnya; `Server: nginx` tanpa nomor versi; tidak ada `X-Powered-By`.

**2. File sensitif diblokir**
```bash
curl -I http://localhost:8080/.env
```
Hasil: `404 Not Found`.

**3. Rate limit login di Nginx** (Git Bash)
```bash
for i in $(seq 1 10); do curl -s -o /dev/null -w "%{http_code} " -X POST http://localhost:8080/login; done
```
Beberapa request pertama dijawab Laravel (419/302), sisanya **429** langsung dari Nginx. Catatannya ada di `infra/logs/nginx/gie-error.log` ("limiting requests").

**4. Hak akses database (least privilege)**
```bash
docker compose exec mysql bash /scripts/harden.sh
```
Masuk sebagai user aplikasi lalu coba merusak data:
```bash
docker compose exec mysql mysql -ugie_app -p gie
```
```sql
DROP TABLE payments;                         -- DROP command denied
UPDATE activity_logs SET description='x';    -- Audit log bersifat append-only: tidak boleh diubah
DELETE FROM activity_logs;                   -- Audit log bersifat append-only: tidak boleh dihapus
TRUNCATE TABLE activity_logs;                -- DROP command denied
DROP TRIGGER activity_logs_no_delete;        -- TRIGGER command denied
```

**5. Disaster recovery (backup & restore database utuh)**
```bash
docker compose exec backup bash /backup.sh now
docker compose exec mysql mysql -uroot -p gie -e "SET FOREIGN_KEY_CHECKS=0; DROP TABLE payments;"
docker compose exec mysql bash /scripts/restore.sh gie-db-XXXXXXXX-XXXXXX.sql.gz
```
Restore mengecek checksum dulu (file yang diubah akan ditolak) dan mencetak lama pemulihan. Angka itu dipakai sebagai **RTO aktual** di laporan.

## Catatan

- Port database hanya terbuka untuk laptop sendiri (`127.0.0.1:3307`), bisa dipakai HeidiSQL/DBeaver dengan user `gie_app`.
- Restore di folder ini memulihkan **seluruh database**, termasuk audit log, ke kondisi saat backup. Catatan audit setelah waktu backup ikut hilang. Di produksi, ini ditutup dengan mengirim log ke sistem terpisah (misalnya ELK/Wazuh).
- Header keamanan sekarang dikirim Nginx; header yang sama dari Laravel otomatis disembunyikan (`fastcgi_hide_header`) supaya tidak dobel. Jika aplikasi dijalankan tanpa Docker (`php artisan serve`), middleware di kode tetap melindungi.
