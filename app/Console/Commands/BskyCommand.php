<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\AtProto\BskySyndicationService;
use Throwable;

/**
 * Command to manage Bluesky / AT Protocol federation, test connections, and syndicate content.
 */
class BskyCommand extends AbstractCommand
{
    #[\Override]
    public function getName(): string
    {
        return 'bsky';
    }

    #[\Override]
    public function getAliases(): array
    {
        return ['bluesky', 'atproto'];
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Manages Bluesky / AT Protocol federation, status posting, and POSSE syndication.';
    }

    #[\Override]
    public function getUsage(): string
    {
        return "php indieinabox.php bsky test\n" .
               "php indieinabox.php bsky syndicate <filepath|slug> [--force]\n" .
               "php indieinabox.php bsky post \"<text>\" [--image=<path>]\n" .
               "php indieinabox.php bsky did";
    }

    #[\Override]
    public function execute(array $argv): int
    {
        $subcommand = $argv[2] ?? '';
        $service = new BskySyndicationService($this->site);

        switch ($subcommand) {
            case 'test':
                return $this->handleTest($service);

            case 'did':
                $config = $service->getConfig();
                if ($config['did'] !== '') {
                    echo $config['did'] . "\n";
                    return 0;
                }
                echo "\033[33mNo ATProto DID currently configured.\033[0m\n";
                return 1;

            case 'syndicate':
                $target = $argv[3] ?? '';
                if ($target === '') {
                    echo "\033[31mError: Target file or slug required.\033[0m\nUsage: php indieinabox.php bsky syndicate <path-or-slug> [--force]\n";
                    return 1;
                }
                $force = $this->hasFlag($argv, '--force');
                return $this->handleSyndicate($service, $target, $force);

            case 'post':
                $text = $argv[3] ?? '';
                if (str_starts_with($text, '--')) {
                    $text = '';
                }
                $image = $this->getOption($argv, 'image');
                $images = $image ? [$image] : [];

                if ($text === '') {
                    echo "\033[31mError: Post text required.\033[0m\nUsage: php indieinabox.php bsky post \"<text>\" [--image=<path>]\n";
                    return 1;
                }
                return $this->handlePost($service, $text, $images);

            default:
                echo "\033[36mBluesky / AT Protocol Federation CLI\033[0m\n\n";
                echo $this->getUsage() . "\n";
                return 1;
        }
    }

    private function handleTest(BskySyndicationService $service): int
    {
        echo "\033[36mTesting AT Protocol authentication with PDS...\033[0m\n";
        $cfg = $service->getConfig();
        echo "Handle:   " . ($cfg['handle'] ?: '(not configured)') . "\n";
        echo "PDS URL:  " . $cfg['pdsUrl'] . "\n";

        if ($cfg['handle'] === '' || $cfg['password'] === '') {
            echo "\033[31mError: Handle and App Password must be configured in Admin Settings.\033[0m\n";
            return 1;
        }

        $res = $service->testCredentials();
        if ($res['success']) {
            echo "\033[32m✔ Authentication successful!\033[0m\n";
            echo "DID:      " . ($res['did'] ?? '') . "\n";
            echo "Handle:   " . ($res['handle'] ?? '') . "\n";
            return 0;
        }

        echo "\033[31m✘ Authentication failed:\033[0m " . ($res['error'] ?? 'Unknown error') . "\n";
        return 1;
    }

    private function handleSyndicate(BskySyndicationService $service, string $target, bool $force): int
    {
        $filePath = $target;
        if (!file_exists($filePath)) {
            // Check in content directory
            $contentDir = $this->site->paths->getContentPath();
            $candidates = [
                $contentDir . DIRECTORY_SEPARATOR . $target,
                $contentDir . DIRECTORY_SEPARATOR . $target . '.md',
                $contentDir . DIRECTORY_SEPARATOR . 'articles' . DIRECTORY_SEPARATOR . $target . '.md',
                $contentDir . DIRECTORY_SEPARATOR . 'notes' . DIRECTORY_SEPARATOR . $target . '.md',
            ];
            $found = false;
            foreach ($candidates as $cand) {
                if (file_exists($cand)) {
                    $filePath = $cand;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                echo "\033[31mError: Could not locate file or note for '{$target}'.\033[0m\n";
                return 1;
            }
        }

        echo "\033[36mSyndicating '{$filePath}' to Bluesky...\033[0m\n";
        try {
            $url = $service->syndicateFile($filePath, $force);
            echo "\033[32m✔ Syndicated successfully!\033[0m\n";
            echo "Bluesky URL: {$url}\n";
            return 0;
        } catch (Throwable $e) {
            echo "\033[31m✘ Syndication failed:\033[0m " . $e->getMessage() . "\n";
            return 1;
        }
    }

    /**
     * @param array<int, string> $images
     */
    private function handlePost(BskySyndicationService $service, string $text, array $images): int
    {
        echo "\033[36mPublishing note to Bluesky...\033[0m\n";
        try {
            $result = $service->syndicatePost($text, 'note', '', null, $images);
            echo "\033[32m✔ Published successfully!\033[0m\n";
            echo "Bluesky URL: " . $result['url'] . "\n";
            return 0;
        } catch (Throwable $e) {
            echo "\033[31m✘ Publishing failed:\033[0m " . $e->getMessage() . "\n";
            return 1;
        }
    }
}
