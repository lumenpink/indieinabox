<?php

declare(strict_types=1);

/**
 * Mock Aperture Microsub Client test harness.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if ($uri === '/test-suite') {
    header('Content-Type: application/json');
    echo json_encode([
        'client' => 'Aperture Test Runner',
        'tests' => [
            'channels' => 'passed',
            'timeline' => 'passed',
            'follow' => 'passed',
            'unfollow' => 'passed',
            'mark_read' => 'passed',
            'mute' => 'passed',
            'websub_push' => 'passed'
        ]
    ]);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['service' => 'aperture-mock', 'status' => 'ready']);
