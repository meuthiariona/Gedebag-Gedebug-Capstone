# Deploy ke Ubuntu Server di VMware (laptop sendiri)

Konfigurasi dan skrip yang sama dengan server online (lihat [DEPLOY.md](DEPLOY.md)), hanya sertifikat HTTPS-nya
**self-signed** karena VM di laptop tidak bisa dijangkau Let's Encrypt. Cocok untuk latihan deploy,
simulasi server, dan demo di laptop. Saat nanti pindah ke Azure, langkahnya hampir sama.

## A. Siapkan di Windows

1. **VMware Workstation Pro** (gratis untuk penggunaan pribadi): buat akun di situs Broadcom → *Software* →
   *VMware Cloud Foundation* → *My Downloads* → *Free Software Downloads* → **VMware Workstation Pro** → pilih versi
   Windows terbaru → unduh & instal.
2. **Ubuntu Server 24.04 LTS**: unduh ISO dari https://ubuntu.com/download/server.

## B. Buat VM

1. VMware → **File → New Virtual Machine** → *Typical* → pilih **I will install the operating system later**.
2. Guest OS: **Linux → Ubuntu 64-bit**. Nama: `gie-server`.
3. Disk: **30 GB**, *Store virtual disk as a single file*.
4. **Customize Hardware**:
   - Memory **4 GB** (minimal 2 GB), Processors **2**
   - New CD/DVD → *Use ISO image file* → pilih ISO Ubuntu Server
   - Network Adapter → **NAT**
5. **Finish** → **Power on this virtual machine**.

## C. Instal Ubuntu Server

Navigasi pakai panah, Spasi untuk mencentang, Enter untuk lanjut.

| Layar | Pilihan |
|---|---|
| Language / Keyboard | English / sesuai keyboard |
| Type of installation | Ubuntu Server |
| Network | Biarkan (DHCP). **Catat IP** yang muncul, misalnya `192.168.153.130` |
| Proxy / Mirror | Biarkan kosong / default |
| Storage | *Use an entire disk* (default) → Done → Continue |
| Profile | Your name `Gie Admin`, server name `gie-server`, username `gie`, password kuat |
| Ubuntu Pro | Skip for now |
| SSH | **Centang Install OpenSSH server** |
| Featured snaps | **Jangan centang apa pun** (Docker dipasang lewat skrip, bukan snap) |

Tunggu sampai muncul **Reboot Now**, lalu tekan Enter (kalau diminta melepas ISO, tekan Enter lagi). Login di
jendela VMware, lalu cek IP: `hostname -I`

## D. Masuk lewat SSH dari Windows (PowerShell)

Lebih nyaman daripada jendela VMware karena bisa copy-paste.

```powershell
# 1. Buat kunci SSH di laptop (sekali; Enter saja untuk semua pertanyaan)
ssh-keygen -t ed25519

# 2. Kirim kunci publik ke VM (masukkan password user gie)
type $env:USERPROFILE\.ssh\id_ed25519.pub | ssh gie@192.168.153.130 "mkdir -p ~/.ssh && chmod 700 ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys"

# 3. Masuk (sekarang tanpa password)
ssh gie@192.168.153.130
```

> Langkah 2 wajib dilakukan **sebelum** setup server, karena setup mematikan login SSH dengan password.

## E. Ambil kode

Pastikan semua patch sudah di-push ke GitHub (lihat bagian 0 di DEPLOY.md), lalu di VM:

```bash
git clone https://github.com/meuthiariona/Gedebag-Gedebug-Capstone.git
cd Gedebag-Gedebug-Capstone
```

## F. Setup server & deploy

```bash
sudo bash infra/deploy/setup-server.sh
exit
```

Login SSH lagi, lalu:

```bash
cd Gedebag-Gedebug-Capstone
bash infra/deploy/deploy.sh 192.168.153.130 --self-signed
```

Ganti `192.168.153.130` dengan IP VM-mu. Proses pertama sekitar 5–10 menit. Di akhir, skrip mencetak **password awal
setiap akun**. Simpan, lalu hapus filenya: `rm infra/.credentials-awal`

## G. Buka di browser Windows

Buka `https://192.168.153.130`. Browser akan menampilkan peringatan *Your connection is not private*, karena
sertifikatnya buatan sendiri dan bukan dari lembaga resmi. Untuk VM lokal ini wajar: klik **Advanced → Proceed**.
Login sebagai `admin@nexa.test` dengan password awal tadi; akun staf langsung diminta ganti password.

## H. Perawatan

| Kebutuhan | Perintah / cara |
|---|---|
| Update kode | `git pull && bash infra/deploy/deploy.sh` |
| Status container | `docker compose -f infra/docker-compose.yml -f infra/docker-compose.prod.yml ps` |
| Log aplikasi | `docker compose -f infra/docker-compose.yml -f infra/docker-compose.prod.yml logs -f app nginx` |
| Matikan VM | `sudo poweroff`. Saat VM dinyalakan lagi, semua container ikut menyala otomatis |
| Titik pemulihan cepat | VMware → **VM → Snapshot → Take Snapshot** sebelum mencoba hal berisiko |

## I. Kalau IP VM berubah

VMware NAT biasanya memberi IP yang sama, tetapi kalau berubah (atau pindah ke mode Bridged supaya bisa dibuka dari HP
di Wi-Fi yang sama):

```bash
cd Gedebag-Gedebug-Capstone/infra
sed -i 's/^DOMAIN=.*/DOMAIN=IP-BARU/' .env
rm -rf certbot
cd .. && bash infra/deploy/deploy.sh
```

## J. Pindah ke server online

Di Azure (DEPLOY.md), langkah E–F sama persis. Bedanya hanya perintah deploy memakai domain dan email:
`bash infra/deploy/deploy.sh <domain> <email>`, sehingga sertifikatnya resmi dari Let's Encrypt.
