#!/bin/bash
set -e
# -------------------------------
# Debug: show DB environment
# -------------------------------
echo "DB_CONNECTION=$DB_CONNECTION"
echo "DB_HOST=$DB_HOST"
echo "DB_DATABASE=$DB_DATABASE"
echo "DB_USERNAME=$DB_USERNAME"
# -------------------------------
# Ensure storage directories exist
# -------------------------------
mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
# -------------------------------
# Force Laravel to use MySQL in production
# -------------------------------
export DB_CONNECTION=mysql
# -------------------------------
# Run migrations
# -------------------------------
echo "Running migrations..."
php artisan migrate --force
# -------------------------------
# Prune expired Sanctum tokens (7 day expiry = 168 hours)
# -------------------------------
php artisan sanctum:prune-expired --hours=168 || true
# -------------------------------
# Create default admin if none exists
# -------------------------------
ADMIN_EMAIL="${PARKHUB_ADMIN_EMAIL:-admin@parkhub.local}"
ADMIN_PASSWORD="${PARKHUB_ADMIN_PASSWORD:-admin}"
php artisan tinker --execute="
\$email = getenv('PARKHUB_ADMIN_EMAIL') ?: 'admin@parkhub.local';
\$password = getenv('PARKHUB_ADMIN_PASSWORD') ?: 'admin';
if (\App\Models\User::where('role', 'admin')->orWhere('role', 'superadmin')->count() === 0) {
    \App\Models\User::create([
        'username' => 'admin',
        'email' => \$email,
        'password' => bcrypt(\$password),
        'name' => 'Admin',
        'role' => 'admin',
        'is_active' => true,
        'preferences' => json_encode(['language' => 'en', 'theme' => 'system', 'notifications_enabled' => true]),
    ]);
    \App\Models\Setting::set('needs_password_change', 'true');
    echo 'Default admin created: ' . \$email;
} else {
    echo 'Admin already exists';
}" 
# -------------------------------
# Demo mode: seed with realistic data
# -------------------------------
if [ "${DEMO_MODE}" = "true" ]; then
    echo "DEMO_MODE=true — running ProductionSimulationSeeder..."
    php artisan db:seed --class=ProductionSimulationSeeder --force || true
    echo "Demo data seeded."
fi
# -------------------------------
# Cache config for production
# -------------------------------
php artisan config:cache
php artisan route:cache
# -------------------------------
# Start Apache
# -------------------------------
exec "$@"
