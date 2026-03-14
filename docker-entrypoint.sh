#!/bin/bash
set -e

# Configure Apache port from PORT env var (default: 10000 for Render, override for self-hosting)
if [ -n "$PORT" ]; then
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf 2>/dev/null || true
    sed -i "s/:80/:$PORT/" /etc/apache2/sites-available/*.conf 2>/dev/null || true
fi

# Generate app key if not provided via environment and not already in .env
if [ -z "$APP_KEY" ]; then
    if [ ! -f .env ]; then
        cp .env.example .env
    fi
    php artisan key:generate --force
    echo "App key generated."
else
    echo "Using APP_KEY from environment."
    # Ensure .env exists so artisan commands work
    if [ ! -f .env ]; then
        cp .env.example .env
    fi
fi

# Ensure storage directories exist
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Run migrations (log errors instead of silencing them)
echo "Running migrations..."
php artisan migrate --force 2>&1 || { echo "WARNING: Migrations failed"; }

# Prune expired Sanctum tokens (7 day expiry = 168 hours)
php artisan sanctum:prune-expired --hours=168 2>&1 || true

# Demo mode: seed with realistic data on every fresh start
# The seeder creates admin users with known credentials (admin@parkhub.test / ParkHub2026!)
if [ "${DEMO_MODE}" = "true" ]; then
    echo "DEMO_MODE=true — running ProductionSimulationSeeder..."
    php artisan db:seed --class=ProductionSimulationSeeder --force 2>&1 || { echo "WARNING: Demo seeding failed"; }
    echo "Demo data seeded."
else
    # Non-demo: create default admin if none exists (works without tinker in --no-dev)
    php artisan parkhub:create-admin 2>&1 || true
fi

# Cache config for production
php artisan config:cache 2>&1 || true
php artisan route:cache 2>&1 || true

exec "$@"
