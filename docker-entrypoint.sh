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

# Override .env with Docker env vars (env vars take precedence over .env.example defaults)
[ -n "$PARKHUB_ADMIN_PASSWORD" ] && sed -i "s|^PARKHUB_ADMIN_PASSWORD=.*|PARKHUB_ADMIN_PASSWORD=$PARKHUB_ADMIN_PASSWORD|" .env
[ -n "$PARKHUB_ADMIN_EMAIL" ] && sed -i "s|^PARKHUB_ADMIN_EMAIL=.*|PARKHUB_ADMIN_EMAIL=$PARKHUB_ADMIN_EMAIL|" .env
[ -n "$DEMO_MODE" ] && (grep -q "^DEMO_MODE=" .env && sed -i "s|^DEMO_MODE=.*|DEMO_MODE=$DEMO_MODE|" .env || echo "DEMO_MODE=$DEMO_MODE" >> .env)
[ -n "$DB_CONNECTION" ] && sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=$DB_CONNECTION|" .env
[ -n "$DB_DATABASE" ] && sed -i "s|^DB_DATABASE=.*|DB_DATABASE=$DB_DATABASE|" .env

# Ensure storage directories exist
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Demo mode: fresh DB + seed with realistic data on every container start
# SEED_DEMO_DATA=true: seed data once in production mode (no demo UI/auto-reset)
# Non-demo: incremental migrations only
if [ "${DEMO_MODE}" = "true" ] || [ "${SEED_DEMO_DATA}" = "true" ]; then
    echo "DEMO_MODE=true — running migrate:fresh + ProductionSimulationSeeder..."
    php artisan migrate:fresh --force 2>&1 || { echo "WARNING: Migrations failed"; }
    php artisan db:seed --class=ProductionSimulationSeeder --force 2>&1 || { echo "WARNING: Demo seeding failed"; }
    echo "Demo data seeded."
else
    echo "Running migrations..."
    php artisan migrate --force 2>&1 || { echo "WARNING: Migrations failed"; }
    # Create default admin if none exists (works without tinker in --no-dev)
    php artisan parkhub:create-admin 2>&1 || true
fi

# Generate VAPID keys for push notifications (once)
php artisan vapid:generate 2>&1 || true

# Prune expired Sanctum tokens (7 day expiry = 168 hours)
php artisan sanctum:prune-expired --hours=168 2>&1 || true

# Cache config for production
php artisan config:cache 2>&1 || true
php artisan route:cache 2>&1 || true

# Start Laravel scheduler in background (needed for auto-release, demo resets, etc.)
# Render free tier doesn't support separate worker processes
(while true; do php artisan schedule:run >> storage/logs/scheduler.log 2>&1; sleep 60; done) &

exec "$@"
