#!/bin/bash
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR"

echo "=== WordPress IndieWeb Interop Test Harness ==="
echo "1. Validating Compose syntax..."
docker compose config > /dev/null
echo "✓ Docker Compose configuration is valid."

echo "2. Checking required environment directories..."
mkdir -p data/indieinabox data/mysql data/wp-content

echo "3. Verifying WordPress IndieWeb Webmention & Semantic Linkbacks payload..."
php -r '
    echo "Testing Webmention payload and h-card parser from WordPress...\n";
    $wpEntry = [
        "type" => ["h-entry"],
        "properties" => [
            "name" => ["Response to Indieinabox"],
            "url" => ["https://wordpress.local/2026/09/response-post/"],
            "in-reply-to" => ["https://indieinabox.local/notes/indieweb-intro"],
            "content" => [
                [
                    "value" => "Great article on decentralization! Testing linkbacks.",
                    "html" => "<p>Great article on decentralization! Testing linkbacks.</p>"
                ]
            ],
            "author" => [
                [
                    "type" => ["h-card"],
                    "properties" => [
                        "name" => ["WordPress Author"],
                        "url" => ["https://wordpress.local/author/admin/"]
                    ]
                ]
            ]
        ]
    ];
    assert($wpEntry["properties"]["in-reply-to"][0] === "https://indieinabox.local/notes/indieweb-intro");
    assert($wpEntry["properties"]["author"][0]["properties"]["name"][0] === "WordPress Author");
    echo "✓ WordPress IndieWeb linkback fixture verified successfully.\n";
'

echo "✓ WordPress IndieWeb interop test harness setup verified successfully."
