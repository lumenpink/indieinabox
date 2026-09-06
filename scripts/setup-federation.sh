#!/bin/bash
set -e

COMMAND=$1

source tests/federation/.env.federation

if [ "$COMMAND" == "--wipe" ]; then
    echo "Wiping federation environment..."
    cd tests/federation
    docker compose down -v
    echo "Environment wiped."
    exit 0
fi

if [ "$COMMAND" == "--seed" ]; then
    echo "Seeding federation environment with test posts..."
    
    # 1. Setup Mastodon
    echo ">> Setting up Mastodon DB and User..."
    docker exec mastodon_web bash -c "RAILS_ENV=production bundle exec rails db:setup"
    docker exec mastodon_web bash -c "RAILS_ENV=production bin/tootctl accounts create admin --email admin@${MASTODON_DOMAIN} --confirmed --role admin"
    
    # 2. Setup Pixelfed
    echo ">> Setting up Pixelfed DB and User..."
    docker exec pixelfed_web php artisan migrate --force
    docker exec pixelfed_web php artisan user:create --name admin --email admin@${PIXELFED_DOMAIN} --username admin --password adminpass --is_admin
    
    # 3. Setup Misskey
    # Misskey DB is initialized automatically by the image, but creating users via CLI is tricky
    # Typically done via API calls once the server is up.
    
    echo "Data seeded! Note: Depending on the platform, you might need to login via web UI to generate tokens for API posts."
    exit 0
fi

echo "Usage: ./setup-federation.sh [--wipe | --seed]"
