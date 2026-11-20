#!/usr/bin/env bash
# ============================================================================
#  Persiapan VM Ubuntu baru (cukup SEKALI). Jalankan:
#     sudo bash infra/deploy/setup-server.sh
#
#  - Update sistem + update keamanan otomatis
#  - Swap 2 GB (VM gratis hanya punya RAM 1 GB)
#  - Docker + Docker Compose
#  - fail2ban: blokir IP yang mencoba menebak password SSH
#  - SSH hanya boleh login dengan kunci (password dimatikan), jika kunci sudah terpasang
# ============================================================================
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "Jalankan dengan sudo."; exit 1; }
USER_NAME="${SUDO_USER:-$(logname 2>/dev/null || echo root)}"
export DEBIAN_FRONTEND=noninteractive

echo ">> [1/5] Update sistem"
apt-get update -q
apt-get upgrade -y -q
apt-get install -y -q git curl ca-certificates openssl fail2ban unattended-upgrades
dpkg-reconfigure -f noninteractive unattended-upgrades   # update keamanan dipasang otomatis

echo ">> [2/5] Swap 2 GB"
if ! swapon --show | grep -q /swapfile; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    sysctl -w vm.swappiness=10 >/dev/null
    echo 'vm.swappiness=10' > /etc/sysctl.d/99-gie-swap.conf
fi
free -h | sed 's/^/   /'

echo ">> [3/5] Docker"
if ! command -v docker >/dev/null; then
    curl -fsSL https://get.docker.com | sh
fi
usermod -aG docker "$USER_NAME"
# Batasi ukuran log container supaya disk tidak penuh
mkdir -p /etc/docker
cat > /etc/docker/daemon.json <<'JSON'
{ "log-driver": "json-file", "log-opts": { "max-size": "10m", "max-file": "3" } }
JSON
systemctl restart docker
docker compose version

echo ">> [4/5] fail2ban (proteksi SSH)"
cat > /etc/fail2ban/jail.d/gie-sshd.local <<'CONF'
[sshd]
enabled  = true
maxretry = 5
findtime = 10m
bantime  = 1h
CONF
systemctl enable --now fail2ban >/dev/null
systemctl restart fail2ban

echo ">> [5/5] SSH: login password dimatikan"
AUTH_KEYS="$(getent passwd "$USER_NAME" | cut -d: -f6)/.ssh/authorized_keys"
if [ -s "$AUTH_KEYS" ]; then
    cat > /etc/ssh/sshd_config.d/99-gie-hardening.conf <<'CONF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
CONF
    systemctl reload ssh 2>/dev/null || systemctl reload sshd
    echo "   SSH sekarang hanya menerima kunci."
else
    echo "   PERINGATAN: kunci SSH belum ada di $AUTH_KEYS, login password dibiarkan aktif."
fi

echo ""
echo ">> Selesai. LOGOUT lalu login SSH lagi (supaya grup docker aktif), kemudian jalankan:"
echo "   bash infra/deploy/deploy.sh <domain> <email>"
