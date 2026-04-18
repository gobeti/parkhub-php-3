#!/usr/bin/env bash
set -euo pipefail

# ParkHub PHP — Quick Setup
# Usage: curl -fsSL https://raw.githubusercontent.com/nash87/parkhub-php/main/install.sh | bash

VERSION="${PARKHUB_VERSION:-latest}"
PORT="${PARKHUB_PORT:-8080}"
ADMIN_EMAIL="${PARKHUB_ADMIN_EMAIL:-admin@parkhub.test}"
ADMIN_PASSWORD="${PARKHUB_ADMIN_PASSWORD:-}"

echo "╔══════════════════════════════════════════════╗"
echo "║          ParkHub PHP — Quick Setup           ║"
echo "╚══════════════════════════════════════════════╝"
echo ""

# Check Docker
if ! command -v docker &>/dev/null; then
  echo "❌ Docker not found. Install: https://docs.docker.com/get-docker/"
  exit 1
fi

if ! docker compose version &>/dev/null; then
  echo "❌ Docker Compose not found. Install: https://docs.docker.com/compose/install/"
  exit 1
fi

echo "✓ Docker + Compose found"
echo "ℹ Includes MySQL database (auto-configured)"

# Generate password if not set
if [ -z "$ADMIN_PASSWORD" ]; then
  ADMIN_PASSWORD=$(openssl rand -base64 16 | tr -dc 'A-Za-z0-9' | head -c16)
  echo "✓ Generated admin password: $ADMIN_PASSWORD"
  echo "  ⚠ Save this password — it won't be shown again!"
fi

# Download docker-compose.yml
echo "→ Downloading docker-compose.yml..."
curl -fsSL "https://raw.githubusercontent.com/nash87/parkhub-php/main/docker-compose.yml" -o docker-compose.yml

# Generate strong database secrets so MySQL 8 can boot (it refuses without a root password)
DB_PASSWORD_GENERATED=$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c24)
MYSQL_ROOT_PASSWORD_GENERATED=$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c24)

# Create .env — DB_HOST must match the compose service name (`db`)
cat > .env <<EOF
PARKHUB_ADMIN_EMAIL=$ADMIN_EMAIL
PARKHUB_ADMIN_PASSWORD=$ADMIN_PASSWORD
DB_CONNECTION=mysql
DB_HOST=db
DB_DATABASE=parkhub
DB_USERNAME=parkhub
DB_PASSWORD=$DB_PASSWORD_GENERATED
MYSQL_PASSWORD=$DB_PASSWORD_GENERATED
MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD_GENERATED
EOF

# Update port if non-default
if [ "$PORT" != "8080" ]; then
  sed -i "s/8080:10000/$PORT:10000/" docker-compose.yml
fi

# Start
echo "→ Starting ParkHub..."
docker compose up -d

echo ""
echo "╔══════════════════════════════════════════════╗"
echo "║  ✅ ParkHub is running!                      ║"
echo "║                                              ║"
echo "║  URL:      http://localhost:$PORT             "
echo "║  Admin:    $ADMIN_EMAIL / $ADMIN_PASSWORD     "
echo "║  API Docs: http://localhost:$PORT/docs/api    "
echo "║                                              ║"
echo "║  Includes: MySQL database (auto-configured)  ║"
echo "║                                              ║"
echo "║  Stop:  docker compose down                  ║"
echo "║  Logs:  docker compose logs -f               ║"
echo "╚══════════════════════════════════════════════╝"
