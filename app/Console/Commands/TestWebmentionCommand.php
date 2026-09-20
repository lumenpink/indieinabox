<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Theme\ThemeData;
use Indieinabox\Webmention\WebmentionSender;

/**
 * Command to test Webmention endpoint discovery, outbound ping delivery, and h-card compliance.
 */
class TestWebmentionCommand extends AbstractCommand
{
    #[\Override]
    public function getName(): string
    {
        return 'test-webmention';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Tests Webmention endpoint discovery, ping transmission, and IndieWebify.me Level 1 h-card markup.';
    }

    #[\Override]
    public function getUsage(): string
    {
        return "Usage:\n" .
               "  php indieinabox.php test-webmention <target-url> [--source <source-url>]\n" .
               "  php indieinabox.php test-webmention --validate-hcard [url-or-file]";
    }

    #[\Override]
    public function execute(array $argv): int
    {
        if ($this->hasFlag($argv, '--validate-hcard')) {
            $this->validateHcard($argv);
            return 0;
        }

        $targetUrl = null;
        $sourceUrl = $this->getOption($argv, 'source');

        foreach ($argv as $i => $arg) {
            if ($i <= 1 || $arg === '--source') {
                continue;
            }
            if (isset($argv[$i - 1]) && $argv[$i - 1] === '--source') {
                continue;
            }
            if (!str_starts_with($arg, '--')) {
                $targetUrl = $arg;
                break;
            }
        }

        if (!$targetUrl) {
            echo $this->getUsage() . "\n";
            return 1;
        }

        echo "Checking target: $targetUrl\n";
        $discovery = WebmentionSender::discoverEndpointDetails($targetUrl);

        if ($discovery['effective_url'] !== $targetUrl) {
            echo "Redirected to: " . $discovery['effective_url'] . "\n";
        }
        echo "HTTP Status: " . $discovery['http_code'] . "\n";

        if ($discovery['endpoint']) {
            $methodDesc = match ($discovery['method']) {
                'http_header' => 'HTTP Link Header',
                'html_link' => 'HTML <link rel="webmention">',
                'html_a' => 'HTML <a rel="webmention">',
                default => 'Unknown',
            };
            echo "Discovered Webmention Endpoint: " . $discovery['endpoint'] . "\n";
            echo "Discovery Method: " . $methodDesc . "\n";

            if ($sourceUrl) {
                echo "\nSending Webmention ping...\n";
                echo "Source: $sourceUrl\n";
                echo "Target: $targetUrl\n";
                $result = WebmentionSender::sendWebmention($discovery['endpoint'], $sourceUrl, $targetUrl);
                echo "Response Code: " . $result['http_code'] . "\n";
                if ($result['success']) {
                    echo "Result: SUCCESS (Webmention accepted)\n";
                } else {
                    $err = $result['error'] ?? ('HTTP ' . $result['http_code']);
                    echo "Result: FAILED ($err)\n";
                }
                if (!empty($result['response'])) {
                    echo "Response Body:\n" . trim(substr($result['response'], 0, 500)) . "\n";
                }
            } else {
                echo "\nTip: To send a live webmention ping to this endpoint, pass --source <source-url>\n";
            }
        } else {
            echo "No Webmention endpoint found for $targetUrl\n";
        }

        return 0;
    }

    /**
     * Validates the presence and completeness of an h-card for IndieWebify.me Level 1.
     *
     * @param array<int, string> $argv
     */
    private function validateHcard(array $argv): void
    {
        $target = null;
        foreach ($argv as $i => $arg) {
            if ($i <= 1 || $arg === '--validate-hcard') {
                continue;
            }
            if (!str_starts_with($arg, '--')) {
                $target = $arg;
                break;
            }
        }

        if ($target && (str_starts_with($target, 'http://') || str_starts_with($target, 'https://'))) {
            $sourceDesc = $target;
            $html = (string) @file_get_contents($target);
        } elseif ($target && file_exists($target)) {
            $sourceDesc = $target;
            $html = (string) file_get_contents($target);
        } else {
            $homeFile = $this->site->paths->outputDirHtml . '/index.html';
            if (file_exists($homeFile)) {
                $sourceDesc = $homeFile;
                $html = (string) file_get_contents($homeFile);
            } else {
                $sourceDesc = 'Default Theme Header';
                $html = ThemeData::getHCard($this->site);
            }
        }

        if (trim($html) === '') {
            echo "Error: Could not load HTML to validate h-card from " . ($sourceDesc ?: 'default source') . "\n";
            return;
        }

        echo "Validating IndieWebify.me Level 1 (h-card) on: $sourceDesc\n\n";

        $parsed = function_exists('\Mf2\parse') ? \Mf2\parse($html, 'http://localhost') : ['items' => []];
        $hcard = null;
        foreach ($parsed['items'] ?? [] as $item) {
            if (in_array('h-card', $item['type'] ?? [], true)) {
                $hcard = $item;
                break;
            }
        }

        if (!$hcard) {
            echo "[FAILED] No element with class 'h-card' found.\n";
            return;
        }

        echo "[OK] Found 'h-card' microformat.\n";

        $props = $hcard['properties'] ?? [];
        $hasName = !empty($props['name'][0]);
        $hasUrl = !empty($props['url'][0]);
        $hasPhoto = !empty($props['photo'][0]);
        $hasNote = !empty($props['note'][0]);

        $nameVal = is_array($props['name'][0] ?? null) ? ($props['name'][0]['value'] ?? '') : ($props['name'][0] ?? '');
        $urlVal = is_array($props['url'][0] ?? null) ? ($props['url'][0]['value'] ?? '') : ($props['url'][0] ?? '');
        $photoVal = is_array($props['photo'][0] ?? null) ? ($props['photo'][0]['value'] ?? '') : ($props['photo'][0] ?? '');
        $noteVal = is_array($props['note'][0] ?? null) ? ($props['note'][0]['value'] ?? '') : ($props['note'][0] ?? '');

        echo ($hasName ? "[OK] Name (p-name): " . $nameVal : "[WARN] Missing Name (p-name)") . "\n";
        echo ($hasUrl ? "[OK] URL (u-url): " . $urlVal : "[WARN] Missing URL (u-url)") . "\n";
        echo ($hasPhoto ? "[OK] Photo (u-photo): " . $photoVal : "[INFO] Photo (u-photo) not present (optional)") . "\n";
        echo ($hasNote ? "[OK] Note (p-note): " . $noteVal : "[INFO] Note (p-note) not present (optional)") . "\n";

        if ($hasName && $hasUrl) {
            echo "\nResult: PASS - IndieWebify.me Level 1 criteria satisfied!\n";
        } else {
            echo "\nResult: INCOMPLETE - IndieWebify.me Level 1 requires at least p-name and u-url.\n";
        }
    }
}
