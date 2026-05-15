# Technology Stack — Mobilia Decor System

## Languages & Versions
- **PHP 8.2+** (primary)
- **JavaScript/ES Modules** (frontend build)
- **Java** (auxiliary ML dashboard scripts in storage/app/ml_dashboard_2025/)

## Framework & Core
- **Laravel 12** (framework principal)
- **Filament 3.3** (admin panel, forms, tables, notifications, widgets)
- **Livewire** (reactive UI via Filament)

## Frontend
- **Tailwind CSS 4** (via @tailwindcss/vite plugin)
- **Vite 7** (build tool)
- **Blade** (templates)
- **Alpine.js** (via Filament/Livewire)

## Database
- **SQLite** (desenvolvimento local)
- **MySQL/MariaDB** (produção)
- **Eloquent ORM** com migrations

## Key Packages
| Package | Purpose |
|---------|---------|
| filament/filament 3.3 | Admin panel completo |
| spatie/laravel-permission 7.2 | Roles & permissions |
| maatwebsite/excel 3.1 | Import/export planilhas Excel |
| laraditz/shopee 1.1 | SDK Shopee API |
| guzzlehttp/guzzle | HTTP client (Bling, ML APIs) |
| laravel/pail | Real-time log viewer |
| laravel/pint | Code style fixer |

## Infrastructure
- **Queue**: database driver (jobs table) para processamento assíncrono
- **Deploy**: GitHub Actions SSH (git pull + composer install + migrate + optimize)
- **Server**: Linux com PHP-FPM + Nginx
- **Local dev**: WAMP64 (Windows)

## Development Commands

```bash
# Setup completo
composer setup

# Dev server (serve + queue + pail + vite em paralelo)
composer dev

# Apenas o servidor
php artisan serve

# Queue worker
php artisan queue:listen --tries=1 --timeout=0

# Build assets
npm run build

# Dev assets (hot reload)
npm run dev

# Testes
composer test

# Code style
./vendor/bin/pint

# Migrations
php artisan migrate

# Cache/optimize
php artisan optimize
php artisan optimize:clear
```

## Configuration Files
- `config/bling.php` — Bling API credentials & settings
- `config/mercadolivre.php` — Mercado Livre OAuth config
- `config/shopee.php` — Shopee API config
- `config/permission.php` — Spatie permission settings

## Environment Variables (key ones)
- `BLING_*` — Bling API credentials (client_id, secret, etc.)
- `ML_*` — Mercado Livre OAuth credentials
- `SHOPEE_*` — Shopee API credentials
- `QUEUE_CONNECTION=database`
- Standard Laravel DB/Mail/Cache vars
