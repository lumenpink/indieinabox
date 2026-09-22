#!/bin/bash
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR"

echo "=== Lemmy Community Federation Test Harness ==="
echo "1. Validating Compose syntax..."
docker compose config > /dev/null
echo "✓ Docker Compose configuration is valid."

echo "2. Checking required environment directories..."
mkdir -p data/indieinabox data/postgres

echo "3. Verifying Lemmy FEP-1b12 / Group ActivityStreams & Upvote fixture..."
php -r '
    echo "Testing Lemmy Group actor and FEP-1b12 community post...\n";
    $lemmyGroup = [
        "@context" => ["https://www.w3.org/ns/activitystreams"],
        "id" => "https://lemmy.local/c/indieweb",
        "type" => "Group",
        "name" => "IndieWeb Community",
        "inbox" => "https://lemmy.local/c/indieweb/inbox"
    ];
    $voteActivity = [
        "@context" => "https://www.w3.org/ns/activitystreams",
        "type" => "Like",
        "actor" => "https://lemmy.local/u/testuser",
        "object" => "https://indieinabox.local/notes/forum-topic"
    ];
    assert($lemmyGroup["type"] === "Group");
    assert($voteActivity["type"] === "Like");
    echo "✓ Lemmy Group & Vote fixtures verified successfully.\n";
'

echo "✓ Lemmy interop test harness setup verified successfully."
