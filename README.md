# PharmaNP

PharmaNP is a Laravel + React pharmacy ERP/POS foundation for Nepal-focused pharmacy and distributor operations.

## Stack

- Laravel 12
- React 19, Vite, Tailwind CSS
- Ant Design
- MySQL/MariaDB for production
- Session/cookie/CSRF auth for same-domain SPA deployment

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Open `/setup` first, create company/store/admin, then sign in.
