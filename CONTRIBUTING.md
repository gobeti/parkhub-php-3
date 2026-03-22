# Contributing to ParkHub

## Development Setup

### Prerequisites
- PHP 8.4+
- Composer 2.x
- Node.js 20+ and npm
- SQLite (default) or MySQL 8+

### Quick Start
```bash
git clone https://github.com/nash87/parkhub-php.git
cd parkhub-php
composer setup
```

This will:
1. Install PHP dependencies
2. Copy `.env.example` to `.env`
3. Generate an application key
4. Run database migrations
5. Install Node.js dependencies
6. Build frontend assets

### Running Locally
```bash
composer dev
```

This starts the PHP dev server, queue worker, log viewer, and Vite dev server concurrently.

### Running Tests
```bash
php artisan test              # All tests
php artisan test tests/Unit   # Unit tests only
php artisan test tests/Feature # Feature tests only
```

### Code Style
ParkHub uses [Laravel Pint](https://laravel.com/docs/pint) for code formatting:
```bash
./vendor/bin/pint           # Auto-fix
./vendor/bin/pint --test    # Check only
```

### Static Analysis
```bash
./vendor/bin/phpstan analyse
```

## Pull Request Guidelines

1. Create a feature branch from `main`
2. Write tests for new functionality
3. Ensure all tests pass: `php artisan test`
4. Ensure code style passes: `./vendor/bin/pint --test`
5. Write a descriptive commit message
6. Open a PR against `main`

## Project Structure

```
app/
  Console/Commands/   # Artisan commands
  Http/Controllers/   # API controllers
  Http/Middleware/     # Request middleware
  Http/Requests/      # Form request validation
  Http/Resources/     # API resource transformers
  Models/             # Eloquent models
  Jobs/               # Queue jobs
  Mail/               # Mail templates
config/               # Configuration files
database/             # Migrations and seeders
routes/
  api.php             # Main API routes
  api_v1.php          # V1 API routes
  modules/            # Per-module route files
tests/
  Feature/            # Integration tests
  Unit/               # Unit tests
parkhub-web/          # React SPA frontend
```

## Module System

ParkHub uses a module toggle system. Each module can be enabled/disabled via environment variables (`MODULE_BOOKINGS=true`, `MODULE_VEHICLES=false`, etc.). Module routes are loaded conditionally from `routes/modules/`.

## License

MIT
