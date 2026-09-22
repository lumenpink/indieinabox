<?php

declare(strict_types=1);

/**
 * Mock Indigenous Micropub Client test harness.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if ($uri === '/test-suite') {
    header('Content-Type: application/json');
    echo json_encode([
        'client' => 'Indigenous Micropub Test Runner',
        'tests' => [
            'create_note' => 'passed',
            'create_article_markdown' => 'passed',
            'upload_photo_media' => 'passed',
            'update_post' => 'passed',
            'delete_post' => 'passed',
            'scope_enforcement' => 'passed'
        ]
    ]);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['service' => 'indigenous-mock', 'status' => 'ready']);
