<?php

declare(strict_types=1);

/**
 * Mock Bridgy server simulating publish endpoints and backfeed webmentions.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if ($uri === '/publish/webmention' || $uri === '/webmention') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $source = (string) ($_POST['source'] ?? '');
        $target = (string) ($_POST['target'] ?? '');

        if ($source === '' || $target === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing source or target']);
            exit;
        }

        http_response_code(201);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'type' => 'post',
            'url' => 'https://bridgy.local/response/12345',
            'source' => $source,
            'target' => $target
        ]);
        exit;
    }
}

// Default status
header('Content-Type: application/json');
echo json_encode(['service' => 'bridgy-mock', 'status' => 'ready']);
