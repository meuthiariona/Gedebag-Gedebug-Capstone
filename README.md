# NEXA Event Production — Laravel 12

Website sewa sound system, lighting, talent & paket event (sesuai mockup).
Semua konten (produk, paket, banner, booking, pembayaran, kontak, rekening, dll.) disimpan di **database**
dan dikelola dari **panel admin `/admin`** — tidak ada data yang ditulis manual di kode.

## Menjalankan

```bash
composer install
copy .env.example .env      # lalu: php artisan key:generate (kalau .env baru)
php artisan migrate --seed  # buat tabel + data contoh
php artisan storage:link    # wajib, untuk foto yang di-upload dari admin
php artisan serve
```

Buka http://127.0.0.1:8000

| Akun contoh | Email | Password |
|---|---|---|
| Admin | admin@nexa.test | password |
| Customer | customer@example.com | password |

> Ganti password akun admin dari menu **Admin → Pengguna** sebelum dipakai sungguhan.

### Pakai MySQL (XAMPP) alih-alih SQLite

1. Nyalakan MySQL di XAMPP, buat database `nexa`.
2. Ubah `.env`:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nexa
   DB_USERNAME=root
   DB_PASSWORD=
   ```
3. `php artisan migrate --seed`

## Fitur

**Customer**
- Beranda: banner slider, tab kategori, produk per kategori, paket unggulan.
- Products: filter kategori & pencarian (header search).
- Packages: filter kategori paket, ketersediaan real-time sesuai tanggal.
- Event Availability Engine: pilih tanggal sewa → stok dihitung dari booking yang aktif.
- Detail paket: galeri, isi paket (hardware / talent / layanan), add-on dengan harga yang berubah langsung.
- Keranjang → Checkout **tanpa login** (isi data diri + detail event & venue) → pesanan tersimpan + jadwal di-hold 48 jam.
- Ringkasan pesanan (`/order/{token}`, bisa dibuka tanpa login) + tombol **Chat via WhatsApp** dengan template pesan otomatis berisi detail pesanan. Negosiasi & deal harga dilakukan di WA; admin lalu mengisi diskon & mencatat pembayaran di panel admin.
- My Bookings: tab upcoming/completed/cancelled, progres pembayaran, filter bulan & pencarian.
- Detail booking: pipeline fulfillment, logistik, peta venue, manifest, billing.
- Invoice siap cetak / simpan PDF (tombol Print / Download PDF).
- Wishlist (ikon ♡).

**Admin (`/admin`)**
- Dashboard: statistik, pembayaran yang menunggu verifikasi, event mendatang.
- Booking: ubah status, tahap pipeline, armada/driver, instruksi site, diskon, DP %, tambah/hapus item,
  verifikasi/tolak bukti bayar, catat pembayaran manual. Verifikasi DP otomatis mengonfirmasi booking.
- CRUD: Produk & Talent, Kategori Produk, Paket (+ isi paket & add-on), Kategori Paket, Banner Beranda, Pengguna.
- Pengaturan: nama brand, logo, kontak, WhatsApp, rekening bank, gambar QRIS, biaya logistik, % DP,
  lama hold, file Tech Specs, teks-teks halaman.

Foto bisa di-upload atau diisi URL. Data contoh memakai foto dari Unsplash — ganti dengan foto asli dari admin.

## Struktur penting

- `app/Http/Controllers/` — halaman publik & customer
- `app/Http/Controllers/Admin/ResourceController.php` — CRUD generik (field form didefinisikan per controller)
- `app/Services/Cart.php`, `app/Services/Availability.php` — keranjang & hitung stok per tanggal
- `resources/views/` — Blade + Tailwind (CDN) + Alpine.js + ikon Lucide
- `database/seeders/DatabaseSeeder.php` — data contoh awal

## Test

```bash
php artisan test
```
