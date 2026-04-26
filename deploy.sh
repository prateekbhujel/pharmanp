#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Server-side deployment script for pharmanp.pratikbhujel.com.np
# Placed at: ~/deploy/pharmanp/deploy.sh
# Upload this file manually once via SSH, then GitHub Actions takes over.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

# ── Config ────────────────────────────────────────────────────────────────────
APP_DIR="$HOME/pharmanp"
ARCHIVE="$HOME/deploy/pharmanp/release.tar.gz"
RELEASES_DIR="$HOME/deploy/pharmanp/releases"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RELEASE_DIR="$RELEASES_DIR/$TIMESTAMP"
PUBLIC_HTML="$HOME/public_html/pharmanp"     # subdomain document root

# ── Create new release dir ────────────────────────────────────────────────────
mkdir -p "$RELEASE_DIR"
tar -xzf "$ARCHIVE" -C "$RELEASE_DIR"

# ── Copy .env from shared (never committed) ───────────────────────────────────
if [ -f "$HOME/deploy/pharmanp/shared/.env" ]; then
    cp "$HOME/deploy/pharmanp/shared/.env" "$RELEASE_DIR/.env"
else
    echo "ERROR: shared .env not found at $HOME/deploy/pharmanp/shared/.env"
    exit 1
fi

# ── Link shared storage ───────────────────────────────────────────────────────
mkdir -p "$HOME/deploy/pharmanp/shared/storage/app/public"
mkdir -p "$HOME/deploy/pharmanp/shared/storage/framework/cache"
mkdir -p "$HOME/deploy/pharmanp/shared/storage/framework/sessions"
mkdir -p "$HOME/deploy/pharmanp/shared/storage/framework/views"
mkdir -p "$HOME/deploy/pharmanp/shared/storage/logs"

rm -rf "$RELEASE_DIR/storage"
ln -sfn "$HOME/deploy/pharmanp/shared/storage" "$RELEASE_DIR/storage"

# ── Set permissions ───────────────────────────────────────────────────────────
chmod -R 755 "$RELEASE_DIR/bootstrap/cache"
chmod 755 "$RELEASE_DIR"

# ── Run artisan tasks ─────────────────────────────────────────────────────────
cd "$RELEASE_DIR"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# ── Atomically swap the live symlink ─────────────────────────────────────────
ln -sfn "$RELEASE_DIR/public" "$PUBLIC_HTML"

# ── Clean up old releases (keep last 3) ──────────────────────────────────────
ls -dt "$RELEASES_DIR"/*/ | tail -n +4 | xargs rm -rf || true

# ── Remove uploaded archive ───────────────────────────────────────────────────
rm -f "$ARCHIVE"

echo "Deployed release $TIMESTAMP to $PUBLIC_HTML"
