#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════════
# ONE-TIME server bootstrap for pharmanp.pratikbhujel.com.np
# Run this ONCE via SSH after first login:
#   ssh pratiknp 'bash -s' < scripts/server-bootstrap.sh
# ═══════════════════════════════════════════════════════════════════════════════
set -euo pipefail

echo "==> Setting up pharmanp deployment structure..."

# ── Directory structure ───────────────────────────────────────────────────────
mkdir -p ~/deploy/pharmanp/releases
mkdir -p ~/deploy/pharmanp/shared/storage/{app/public,framework/{cache,sessions,views},logs}

# ── Copy the deploy script to the right place ─────────────────────────────────
# (GitHub Actions will also keep it in place after first deploy)
cp ~/deploy/pharmanp/deploy.sh ~/deploy/pharmanp/deploy.sh 2>/dev/null || true
chmod +x ~/deploy/pharmanp/deploy.sh

# ── Create subdomain document root symlink target ─────────────────────────────
# In cPanel: Add subdomain pharmanp.pratikbhujel.com.np
#   → Document Root: public_html/pharmanp
# This directory starts as a placeholder; deploy.sh will replace it with a symlink.
mkdir -p ~/public_html/pharmanp
cat > ~/public_html/pharmanp/index.html << 'HTML'
<!DOCTYPE html>
<html>
<head><title>PharmanP — Deploying...</title></head>
<body style="font-family:sans-serif;text-align:center;padding:80px">
  <h1>PharmanP</h1>
  <p>Deployment in progress. Check back shortly.</p>
</body>
</html>
HTML

# ── Create shared .env ────────────────────────────────────────────────────────
if [ ! -f ~/deploy/pharmanp/shared/.env ]; then
    cat > ~/deploy/pharmanp/shared/.env << 'ENVFILE'
APP_NAME="PharmanP"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://pharmanp.pratikbhujel.com.np

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=FILL_ME
DB_USERNAME=FILL_ME
DB_PASSWORD=FILL_ME

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=pharmanp.pratikbhujel.com.np

FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@pratikbhujel.com.np"
MAIL_FROM_NAME="PharmanP"

VITE_APP_NAME="PharmanP"
ENVFILE
    echo "==> Created ~/deploy/pharmanp/shared/.env"
    echo "    IMPORTANT: Edit it now with your MySQL creds and APP_KEY"
    echo "    Run: php artisan key:generate --show  (on local) to get a key"
fi

echo ""
echo "==> Done! Next steps:"
echo ""
echo "  1. Edit ~/deploy/pharmanp/shared/.env with real DB creds + APP_KEY"
echo "  2. In cPanel → MySQL → create database + user: pratikb_pharmanp"
echo "  3. In cPanel → Subdomains → pharmanp.pratikbhujel.com.np"
echo "     Document Root: public_html/pharmanp"
echo "  4. Add GitHub Secrets (Settings → Secrets → Actions):"
echo "     DEPLOY_HOST   = your cPanel hostname / IP"
echo "     DEPLOY_USER   = your cPanel username"
echo "     DEPLOY_SSH_KEY = contents of ~/.ssh/id_ed25519 (private key)"
echo "     DEPLOY_PORT   = 22 (or your SSH port)"
echo "  5. Push to main — GitHub Actions does the rest."
echo ""
echo "  To run demo data after first deploy:"
echo "    ssh pratiknp 'cd \$(ls -dt ~/deploy/pharmanp/releases/*/ | head -1) && php artisan db:seed --class=DemoSeeder'"
