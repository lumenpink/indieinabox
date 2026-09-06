<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap/app.php';

$fetcher = new \Indieinabox\FeedFetcher();

$reflection = new \ReflectionClass($fetcher);
$method = $reflection->getMethod('fetchApJson');
$method->setAccessible(true);

$ctx = stream_context_create(['http' => ['header' => "Accept: application/activity+json\r\nUser-Agent: Indieinabox/1.0\r\n"]]);
$data = $method->invoke($fetcher, 'https://gram.social/users/pebbles_chaoskittens/outbox?page=1', $ctx);

echo $data;
