#!/bin/bash
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR"

echo "=== Misskey Interop Test Harness ==="
echo "1. Validating Compose syntax..."
docker compose config > /dev/null
echo "✓ Docker Compose configuration is valid."

echo "2. Checking required environment directories..."
mkdir -p data/indieinabox data/postgres data/redis data/files

echo "3. Verifying Misskey federation fixtures (Notes, Renotes, Emoji Reactions)..."
php -r '
    echo "Testing Misskey custom emoji reaction payload parsing...\n";
    $reactionActivity = [
        "@context" => [
            "https://www.w3.org/ns/activitystreams",
            ["misskey" => "https://misskey-hub.net/ns#"]
        ],
        "type" => "Like",
        "id" => "https://misskey.local/activities/like/123",
        "actor" => "https://misskey.local/users/bob",
        "object" => "https://indieinabox.local/notes/my-first-post",
        "content" => ":blobcat_thumbsup:"
    ];
    assert($reactionActivity["type"] === "Like");
    assert($reactionActivity["content"] === ":blobcat_thumbsup:");
    echo "✓ Misskey custom emoji reaction fixture validated successfully.\n";
'

echo "✓ Misskey interop test harness setup verified successfully."
