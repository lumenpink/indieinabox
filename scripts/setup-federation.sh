#!/bin/bash
set -e

COMMAND=$1

if [ "$COMMAND" == "--wipe" ]; then
    echo "Wiping federation environment..."
    docker compose -f docker-compose.federation.yml down -v
    echo "Environment wiped."
    exit 0
fi

if [ "$COMMAND" == "--seed" ]; then
    echo "Seeding federation environment with test posts..."
    
    # Exemplo: Mastodon CLI
    # docker exec federation_mastodon tootctl accounts create admin --email admin@${MASTODON_DOMAIN} --confirmed
    
    # Exemplo: Pixelfed CLI
    # docker exec federation_pixelfed php artisan user:create ...
    
    echo "Data seeded! You can now test ActivityPub federation."
    exit 0
fi

echo "Usage: ./setup-federation.sh [--wipe | --seed]"
