#!/usr/bin/env bash
# Membuat project Laravel baru lalu menempelkan kode Amanah ke dalamnya.
# Pemakaian (Linux/macOS/Git Bash):  bash scripts/install.sh [nama-folder]
set -e
APP="${1:-amanah-app}"
SRC="$(cd "$(dirname "$0")/.." && pwd)"

composer create-project laravel/laravel:^12.0 "$APP"
cp -R "$SRC/app" "$SRC/bootstrap" "$SRC/config" "$SRC/database" "$SRC/routes" "$SRC/tests" "$SRC/docs" "$SRC/phpunit.xml" "$SRC/.env.example" "$SRC/README.md" "$APP"/
cd "$APP"
composer require laravel/sanctum
cp .env.example .env
php artisan key:generate

echo ""
echo "Selesai. Langkah berikutnya:"
echo "  1. cd $APP  &&  edit .env (DB_DATABASE, DB_USERNAME, DB_PASSWORD)"
echo "  2. Buat database kosong 'amanah_db' di MySQL/PostgreSQL"
echo "  3. php artisan migrate --seed"
echo "  4. php artisan serve   (API di http://localhost:8000/api/v1)"
echo "  5. php artisan test"
