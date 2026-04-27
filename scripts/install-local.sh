#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

missing=()
command -v php >/dev/null 2>&1 || missing+=("php")
command -v composer >/dev/null 2>&1 || missing+=("composer")
command -v npm >/dev/null 2>&1 || missing+=("npm")

if [ "${#missing[@]}" -gt 0 ]; then
  printf 'Missing required tools: %s\n' "${missing[*]}"
  printf 'Install the missing tools first, then run this script again.\n'
  exit 1
fi

if [ ! -f .env ]; then
  cp .env.example .env
fi

mkdir -p database storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
touch database/database.sqlite

composer install
php artisan key:generate --force
php artisan migrate --force

npm install
npm run build

printf '\nPharmaNP is installed.\n'
printf 'For XAMPP Apache, open: http://localhost/pharmanp\n'
printf 'For Laravel dev server, run: php artisan serve\n'
