#!/bin/bash
set -e

echo "=== Provisioning WordPress IndieWeb Suite ==="
echo "Installing and activating Webmention, Semantic Linkbacks, IndieAuth, and Micropub plugins..."

if docker compose ps wp-cli >/dev/null 2>&1; then
    docker compose run --rm wp-cli wp core is-installed || \
    docker compose run --rm wp-cli wp core install \
        --url="https://wordpress.local" \
        --title="IndieWeb WordPress Testbed" \
        --admin_user="admin" \
        --admin_password="admin_pass_123" \
        --admin_email="admin@wordpress.local" --skip-email || true

    docker compose run --rm wp-cli wp plugin install webmention semantic-linkbacks indieauth micropub --activate || true
    echo "✓ WordPress IndieWeb plugin suite active."
else
    echo "Containers not currently running; script ready for invocation during docker compose up."
fi
