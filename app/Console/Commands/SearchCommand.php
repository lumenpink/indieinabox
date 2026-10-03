<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Search\SearchService;
use Indieinabox\SiteBuilder\SiteBuilder;

/**
 * Command to search posts, garden taxonomy, and feeds using SQLite FTS5.
 */
class SearchCommand extends AbstractCommand
{
    #[\Override]
    public function getName(): string
    {
        return 'search';
    }

    #[\Override]
    public function getAliases(): array
    {
        return ['find'];
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Performs full-text search across posts, digital garden, and feeds using SQLite FTS5.';
    }

    #[\Override]
    public function getUsage(): string
    {
        return 'php indieinabox.php search <query> [--type=post|garden|feed] [--lang=<code>] [--limit=<n>] [--reindex] [--json]';
    }

    #[\Override]
    public function execute(array $argv): int
    {
        $searchService = new SearchService(null, $this->site);

        // Handle --reindex flag
        if ($this->hasFlag($argv, '--reindex')) {
            echo "\033[36mReindexing all posts and feeds into SQLite FTS5...\033[0m\n";
            $builder = new SiteBuilder($this->site);
            $builder->scan($this->site->paths->getContentPath());
            $builder->ensureMandatoryHomepage();
            $pages = $builder->getPages();
            $stats = $searchService->rebuildIndex($pages);
            echo "\033[32mReindex complete! Indexed {$stats['posts']} posts and {$stats['feeds']} feed items.\033[0m\n";
        }

        // Extract query terms (ignore options and flags)
        $queryWords = [];
        $skipNext = false;
        foreach (array_slice($argv, 2) as $arg) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }
            if ($arg === '--type' || $arg === '--lang' || $arg === '--limit') {
                $skipNext = true;
                continue;
            }
            if (str_starts_with($arg, '--')) {
                continue;
            }
            $queryWords[] = $arg;
        }

        $query = trim(implode(' ', $queryWords));
        if ($query === '') {
            if ($this->hasFlag($argv, '--reindex')) {
                return 0;
            }
            echo "\033[33mUsage: " . $this->getUsage() . "\033[0m\n";
            return 1;
        }

        $type = $this->getOption($argv, 'type');
        $lang = $this->getOption($argv, 'lang');
        $limitStr = $this->getOption($argv, 'limit');
        $limit = $limitStr !== null ? (int) $limitStr : 15;

        $options = [
            'type' => $type,
            'lang' => $lang,
            'limit' => $limit,
        ];

        $res = $searchService->search($query, $options);

        if ($this->hasFlag($argv, '--json')) {
            echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            return 0;
        }

        echo "\n\033[1;37mSearch results for \033[33m\"{$query}\"\033[1;37m ({$res['total']} found):\033[0m\n\n";

        if (empty($res['results'])) {
            echo "  \033[90mNo matching results found.\033[0m\n\n";
            return 0;
        }

        foreach ($res['results'] as $i => $item) {
            $num = $i + 1;
            $typeTag = match ($item['type']) {
                'garden' => "\033[42;30m GARDEN \033[0m",
                'feed' => "\033[43;30m FEED \033[0m",
                'article' => "\033[44;37m ARTICLE \033[0m",
                'note' => "\033[45;37m NOTE \033[0m",
                default => "\033[46;30m " . strtoupper((string) $item['type']) . " \033[0m",
            };

            $title = $item['title'];
            $url = $item['url'];
            $date = substr((string) ($item['published_at'] ?? ''), 0, 10);
            $author = $item['author'] ? " by \033[37m{$item['author']}\033[0m" : '';

            // Format snippet: replace <mark> with ANSI yellow highlight
            $snippet = str_replace(
                ['<mark>', '</mark>'],
                ["\033[1;33m", "\033[0;90m"],
                (string) ($item['snippet'] ?? '')
            );

            echo "  {$typeTag} \033[1m{$num}. {$title}\033[0m{$author} \033[90m({$date})\033[0m\n";
            if ($snippet !== '') {
                echo "     \033[90m\"{$snippet}\"\033[0m\n";
            }
            if ($url !== '') {
                echo "     \033[34m{$url}\033[0m\n";
            }
            if (!empty($item['tags'])) {
                $tagsFormatted = implode(' ', array_map(fn($t) => "#{$t}", $item['tags']));
                echo "     \033[36m{$tagsFormatted}\033[0m\n";
            }
            echo "\n";
        }

        return 0;
    }
}
