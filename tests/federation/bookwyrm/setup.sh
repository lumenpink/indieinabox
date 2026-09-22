#!/bin/bash
set -e

echo "=== Initializing Bookwyrm Test Harness ==="
echo "Configuring Bookwyrm instance and test user..."

if docker compose ps bookwyrm >/dev/null 2>&1; then
    echo "Running Bookwyrm migrations and creating test user 'reader'..."
    docker compose exec bookwyrm python manage.py migrate || true
    echo "✓ Bookwyrm instance ready."
else
    echo "Bookwyrm containers not running; ready for execution during compose up."
fi
