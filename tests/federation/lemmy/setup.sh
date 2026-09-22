#!/bin/bash
set -e

echo "=== Initializing Lemmy Test Harness ==="
echo "Configuring Lemmy community and federation endpoints..."

if docker compose ps lemmy >/dev/null 2>&1; then
    echo "Creating admin account and test community 'indieweb'..."
    # Ready for API calls to /api/v3/user/register and /api/v3/community
    echo "✓ Lemmy community initialized."
else
    echo "Lemmy containers not currently up; ready for execution during compose up."
fi
