<?php

declare(strict_types=1);

namespace Indieinabox\AtProto;

/**
 * Class AtProtoFacetParser
 *
 * Extracts rich text facets (links, hashtags, mentions) from post content
 * with exact UTF-8 byte slice indices required by the AT Protocol lexicon.
 */
class AtProtoFacetParser
{
    /**
     * Parses text and extracts an array of AT Protocol facets.
     *
     * @param string $text
     * @param array<string, string> $mentionDids Optional map of handle => DID for mentions.
     * @return array<int, array{index: array{byteStart: int, byteEnd: int}, features: array<int, array<string, mixed>>}>
     */
    public static function parse(string $text, array $mentionDids = []): array
    {
        $facets = [];

        // 1. Extract URLs: http:// or https://
        if (preg_match_all('/https?:\/\/[^\s<>"\'\)]+/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $rawUrl = (string) $match[0];
                $byteStart = (int) $match[1];
                
                // Strip trailing punctuation if accidentally captured
                $cleanUrl = rtrim($rawUrl, '.,;:!?');
                $trailingBytes = strlen($rawUrl) - strlen($cleanUrl);
                $byteEnd = $byteStart + strlen($rawUrl) - $trailingBytes;

                $facets[] = [
                    'index' => [
                        'byteStart' => $byteStart,
                        'byteEnd' => $byteEnd,
                    ],
                    'features' => [
                        [
                            '$type' => 'app.bsky.richtext.facet#link',
                            'uri' => $cleanUrl,
                        ]
                    ]
                ];
            }
        }

        // 2. Extract Hashtags: #tag (supports Unicode letters/numbers)
        if (preg_match_all('/(?:^|(?<=[\s\p{P}]))#([\p{L}\p{N}_]+)/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $i => $fullMatch) {
                $tagText = (string) $matches[1][$i][0];
                $byteStart = (int) $fullMatch[1];
                $byteEnd = $byteStart + strlen((string) $fullMatch[0]);

                $facets[] = [
                    'index' => [
                        'byteStart' => $byteStart,
                        'byteEnd' => $byteEnd,
                    ],
                    'features' => [
                        [
                            '$type' => 'app.bsky.richtext.facet#tag',
                            'tag' => $tagText,
                        ]
                    ]
                ];
            }
        }

        // 3. Extract Mentions: @handle.bsky.social or @domain.com
        if (preg_match_all('/(?:^|(?<=[\s\p{P}]))@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $i => $fullMatch) {
                $handle = (string) $matches[1][$i][0];
                $byteStart = (int) $fullMatch[1];
                $byteEnd = $byteStart + strlen((string) $fullMatch[0]);

                if (isset($mentionDids[$handle])) {
                    $facets[] = [
                        'index' => [
                            'byteStart' => $byteStart,
                            'byteEnd' => $byteEnd,
                        ],
                        'features' => [
                            [
                                '$type' => 'app.bsky.richtext.facet#mention',
                                'did' => $mentionDids[$handle],
                            ]
                        ]
                    ];
                }
            }
        }

        // Sort facets ascending by byteStart
        usort($facets, fn($a, $b) => $a['index']['byteStart'] <=> $b['index']['byteStart']);

        return $facets;
    }
}
