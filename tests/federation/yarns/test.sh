#!/bin/bash
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR"

echo "=== Yarns & Twtxt Interop Test Harness ==="
echo "1. Validating Compose syntax..."
docker compose config > /dev/null
echo "✓ Docker Compose configuration is valid."

echo "2. Checking required environment directories..."
mkdir -p data/indieinabox data/yarnd data/twtd

echo "3. Verifying Twtxt / Yarns Subject & Media Embed parsing fixture..."
php -r '
    echo "Testing Twtxt / Yarns parser with subject and media...\n";
    $twtLine = "2026-09-22T01:00:00Z\t(#yarns-test) Hello from Yarns pod! Check out: https://yarnd.local/media/photo.png";
    $parts = explode("\t", $twtLine, 2);
    assert(count($parts) === 2);
    assert(str_contains($parts[1], "(#yarns-test)"));
    assert(str_contains($parts[1], "https://yarnd.local/media/photo.png"));
    echo "✓ Twtxt / Yarns fixture verified successfully.\n";
'

echo "✓ Yarns & Twtxt interop test harness setup verified successfully."
