<?php
require 'vendor/autoload.php';

$config = require __DIR__ . '/.config.php';
\Indieinabox\Core\Database::$dataDir = dirname($config['db_path']);
\Indieinabox\Core\Database::connect($config['db_path']);

$fetcher = new \Indieinabox\FeedFetcher();

// Run fetch for pebbles_chaoskittens
$channel = 'test';
$url = 'https://gram.social/users/pebbles_chaoskittens';

echo "Fetching $url...\n";
$fetcher->fetchFeed($channel, $url);

echo "Done fetching!\n";
echo "Check public_media/microsub/ directory for downloaded media.\n";
