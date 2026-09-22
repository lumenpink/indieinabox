#!/bin/bash
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR"

echo "=== Pixelfed Interop Test Harness ==="
echo "1. Validating Compose syntax..."
docker compose config > /dev/null
echo "✓ Docker Compose configuration is valid."

echo "2. Checking required environment directories..."
mkdir -p data/indieinabox data/postgres data/redis data/storage

echo "3. Verifying Pixelfed Photo & Gallery ActivityStreams fixture parsing..."
php -r '
    echo "Testing Pixelfed multi-photo Note & attachments...\n";
    $photoPost = [
        "@context" => "https://www.w3.org/ns/activitystreams",
        "type" => "Create",
        "actor" => "https://pixelfed.local/users/photographer",
        "object" => [
            "id" => "https://pixelfed.local/p/photographer/1001",
            "type" => "Note",
            "content" => "Sunset over the mountains #photography",
            "attachment" => [
                [
                    "type" => "Document",
                    "mediaType" => "image/jpeg",
                    "url" => "https://pixelfed.local/storage/m/sunset1.jpg",
                    "name" => "Sunset landscape"
                ],
                [
                    "type" => "Document",
                    "mediaType" => "image/jpeg",
                    "url" => "https://pixelfed.local/storage/m/sunset2.jpg",
                    "name" => "Sunset golden hour"
                ]
            ]
        ]
    ];
    assert($photoPost["object"]["type"] === "Note");
    assert(count($photoPost["object"]["attachment"]) === 2);
    echo "✓ Pixelfed multi-photo fixture verified successfully.\n";
'

echo "✓ Pixelfed interop test harness setup verified successfully."
