<?php

declare(strict_types=1);

namespace Indieinabox\Micropub;

use Indieinabox\Site\Site;

/**
 * Class QueryHandler
 *
 * Handles Micropub GET queries such as 'q=config', 'q=syndicate-to', and 'q=source'.
 */
class QueryHandler
{
    /**
     * Executes a Micropub GET query and returns the response payload.
     *
     * @param Site $site Global site configuration.
     * @param string $query The 'q' parameter value.
     * @return array{status: int, headers: array<string, string>, body?: array<string, mixed>, error?: string, error_description?: string}
     */
    public static function handle(Site $site, string $query): array
    {
        $mediaEndpoint = rtrim($site->fqdn ?? '', '/') . '/micropub/media';

        if ($query === 'config') {
            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'body' => [
                    'media-endpoint' => $mediaEndpoint,
                    'syndicate-to' => [],
                ],
            ];
        }

        if ($query === 'syndicate-to') {
            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'body' => [
                    'syndicate-to' => [],
                ],
            ];
        }

        return [
            'status' => 400,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'error' => 'Invalid Query',
            'error_description' => 'Unsupported q parameter.',
        ];
    }
}
