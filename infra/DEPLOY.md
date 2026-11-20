# Deploy gratis ke Azure for Students

Prototype dijalankan di **satu VM Ubuntu** di Azure, memakai stack Docker yang sama dengan lokal
(Nginx + PHP-FPM + MySQL + backup), ditambah **HTTPS gratis** dari Let's Encrypt.

```
 internet ──► :443 nginx (HTTPS, HSTS, header keamanan, rate limit) ──► app ──► mysql (tidak terbuka ke internet)
          ──► :80  nginx (hanya verifikasi sertifikat, sisanya dialihkan ke HTTPS)
 VM: update keamanan otomatis, swap, fail2ban SSH, SSH hanya dengan kunci
```

## Biaya

- **Azure for Students**: kredit **USD 100**, berlaku 12 bulan, **tanpa kartu kredit**, daftar dengan email kampus.
- VM ukuran **B1s** (atau **B2ats v2**) termasuk layanan gratis **750 jam/bulan** selama 12 bulan, cukup untuk satu VM menyala terus.
- Komponen pendukung seperti alamat IP publik dan disk bisa memakai sedikit kredit. Pantau di **Cost Management** portal Azure. Selama kredit belum habis, kamu tidak ditagih.

## 0. Sebelum mulai: push kode terbaru ke GitHub

Repo GitHub saat ini masih berisi commit awal. Pasang semua patch (RBAC, audit trail, hardening login,
backup, perbaikan ZAP, infrastruktur, deploy) secara berurutan, lalu push:

```bash
cd Gedebag-Gedebug-Capstone
git am /path/ke/gie-patches/*.patch
git push
```

## 1. Aktifkan Azure for Students

1. Buka https://azure.microsoft.com/free/students lalu klik **Start free**.
2. Login dengan **email kampus Telkom University** dan ikuti verifikasi.
3. Setelah aktif, buka https://portal.azure.com. Langganan bernama **Azure for Students** akan muncul.

## 2. Buat VM

Portal Azure → **Virtual machines** → **Create** → **Azure virtual machine**.

| Isian | Nilai |
|---|---|
| Subscription | Azure for Students |
| Resource group | Create new → `gie-capstone` |
| Virtual machine name | `gie-capstone` |
| Region | **(Asia Pacific) Southeast Asia** (Singapura, paling dekat). Jika ukuran B1s tidak tersedia, coba East Asia |
| Image | **Ubuntu Server 24.04 LTS - x64 Gen2** |
| Size | **Standard_B1s** (1 vCPU, 1 GB). Alternatif gratis: **Standard_B2ats_v2** |
| Authentication type | **SSH public key** |
| Username | `azureuser` |
| SSH public key source | Generate new key pair → nama `gie-key` |
| Public inbound ports | Allow selected ports → **SSH (22), HTTP (80), HTTPS (443)** |

Tab **Disks**: OS disk type **Standard SSD**. Klik **Review + create** → **Create**, lalu **Download private key**.
Simpan file `gie-key.pem`; file ini tidak bisa diunduh ulang.

## 3. Beri nama domain gratis

1. Buka VM → **Overview** → klik alamat **Public IP address**.
2. **Settings → Configuration** → isi **DNS name label**, misalnya `gie-capstone` → **Save**.
3. Domainmu menjadi: `gie-capstone.southeastasia.cloudapp.azure.com`

## 4. Masuk ke VM

Di PowerShell (Windows) atau Terminal:

```bash
ssh -i C:\Users\NAMA\Downloads\gie-key.pem azureuser@gie-capstone.southeastasia.cloudapp.azure.com
```

Jika Windows menolak dengan pesan *UNPROTECTED PRIVATE KEY FILE*, rapikan izin file kuncinya dulu (PowerShell):

```powershell
icacls .\gie-key.pem /inheritance:r /grant:r "$($env:USERNAME):(R)"
```

## 5. Siapkan server (sekali)

```bash
git clone https://github.com/meuthiariona/Gedebag-Gedebug-Capstone.git
cd Gedebag-Gedebug-Capstone
sudo bash infra/deploy/setup-server.sh
exit
```

Login SSH lagi (langkah 4) supaya izin Docker aktif.

## 6. Deploy

```bash
cd Gedebag-Gedebug-Capstone
bash infra/deploy/deploy.sh gie-capstone.southeastasia.cloudapp.azure.com email-kamu@student.telkomuniversity.ac.id
```

Proses pertama memakan waktu sekitar 5–10 menit (build image, migrasi, sertifikat). Setelah selesai, skrip mencetak
**password awal setiap akun**. Simpan password itu, lalu hapus filenya:

```bash
rm infra/.credentials-awal
```

Buka `https://gie-capstone.southeastasia.cloudapp.azure.com`. Akun staf langsung diminta mengganti password.

## Perawatan

| Kebutuhan | Perintah (dari folder project di VM) |
|---|---|
| Update kode setelah push baru | `git pull && bash infra/deploy/deploy.sh` |
| Lihat status container | `docker compose -f infra/docker-compose.yml -f infra/docker-compose.prod.yml ps` |
| Lihat log Nginx | `tail -f infra/logs/nginx/gie-access.log` |
| Backup manual | `docker compose -f infra/docker-compose.yml -f infra/docker-compose.prod.yml exec backup bash /backup.sh now` |
| Salin backup ke laptop (dijalankan di laptop) | `scp -i gie-key.pem azureuser@DOMAIN:Gedebag-Gedebug-Capstone/infra/backups/* .` |

Sertifikat HTTPS diperpanjang otomatis setiap Senin pukul 03:00. Update keamanan Ubuntu juga terpasang otomatis.

## Simpan baik-baik

- `gie-key.pem`: satu-satunya kunci untuk masuk ke VM (login password dimatikan).
- `infra/.env` di VM: berisi password database acak. Salin ke tempat aman:
  `scp -i gie-key.pem azureuser@DOMAIN:Gedebag-Gedebug-Capstone/infra/.env ./gie-env-backup.txt`

## Bukti untuk laporan

- `https://www.ssllabs.com/ssltest/` → masukkan domain → nilai TLS (target A).
- `https://securityheaders.com` → masukkan domain → nilai header keamanan.
- Scan OWASP ZAP ke URL HTTPS sungguhan (bandingkan dengan scan localhost).
- `sudo fail2ban-client status sshd` → jumlah IP yang diblokir karena mencoba menebak login SSH.
