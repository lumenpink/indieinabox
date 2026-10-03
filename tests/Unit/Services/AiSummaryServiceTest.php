<?php

declare(strict_types=1);

use Indieinabox\Services\AiSummaryService;

it('computes clean body hash ignoring frontmatter and formatting', function () {
    $markdownWithFrontmatter = <<<MD
---
title: "My Article"
date: "2026-10-03"
---
This is the body content with [a link](https://example.com) and some **bold text**.
MD;

    $plainMarkdown = "This is the body content with a link and some bold text.";

    $hash1 = AiSummaryService::computeBodyHash($markdownWithFrontmatter);
    $hash2 = AiSummaryService::computeBodyHash($plainMarkdown);

    expect($hash1)->toBe($hash2);
    expect(strlen($hash1))->toBe(16);
});

it('detects when summary is stale or missing', function () {
    $body = "Original post content here.";
    $hash = AiSummaryService::computeBodyHash($body);

    // Missing summary
    expect(AiSummaryService::isSummaryStale([], $body))->toBeTrue();

    // Summary present with matching hash -> not stale
    $upToDateFrontmatter = [
        'summary' => 'Good summary',
        'summary_hash' => $hash,
        'summary_origin' => 'ai',
    ];
    expect(AiSummaryService::isSummaryStale($upToDateFrontmatter, $body))->toBeFalse();

    // Body edited -> hash changed -> stale!
    $editedBody = "Original post content here with new additions.";
    expect(AiSummaryService::isSummaryStale($upToDateFrontmatter, $editedBody))->toBeTrue();

    // Human-authored summary -> never stale, even if text changes!
    $authorFrontmatter = [
        'summary' => 'My manual author summary',
        'summary_origin' => 'author',
    ];
    expect(AiSummaryService::isSummaryStale($authorFrontmatter, $editedBody))->toBeFalse();

    // AI-revised summary -> protected and never stale
    $revisedFrontmatter = [
        'summary' => 'AI summary refined by human author',
        'summary_origin' => 'ai-revised',
    ];
    expect(AiSummaryService::isSummaryStale($revisedFrontmatter, $editedBody))->toBeFalse();
});

it('synchronizes and persists summary and summary_hash into a physical Markdown file', function () {
    $tempDir = sys_get_temp_dir() . '/ai_summary_test_' . uniqid('', true);
    mkdir($tempDir, 0777, true);
    $filePath = $tempDir . '/my-note.md';

    $initialContent = <<<MD
---
title: "Gardening In Cyberspace"
date: "2026-10-03"
---
A thoughtful note about cultivating digital gardens and decentralization.
MD;

    file_put_contents($filePath, $initialContent);

    // Mock transport for Gemini generateContent
    $mockTransport = function (string $url, array $headers, string $payload) {
        return [
            'code' => 200,
            'body' => json_encode([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Uma nota reflexiva sobre cultivar jardins digitais e descentralização.']
                            ]
                        ]
                    ]
                ]
            ]),
            'error' => ''
        ];
    };

    $service = new AiSummaryService('fake-key', 'gemini-3.5-flash', null, $mockTransport);

    $updated = $service->syncFile($filePath);
    expect($updated)->toBeTrue();

    $reloaded = (string) file_get_contents($filePath);
    expect($reloaded)->toContain('summary: "Uma nota reflexiva sobre cultivar jardins digitais e descentralização."');
    expect($reloaded)->toContain('summary_hash:');
    expect($reloaded)->toContain('summary_origin: "ai"');

    // Second sync immediately after should detect not stale and return false
    $secondSync = $service->syncFile($filePath);
    expect($secondSync)->toBeFalse();

    // Cleanup
    unlink($filePath);
    rmdir($tempDir);
});
