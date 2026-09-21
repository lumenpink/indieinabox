<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Override;
use Indieinabox\Core\Container;
use Indieinabox\Repositories\Contracts\MicrosubRepositoryInterface;
use Indieinabox\Repositories\SqliteMicrosubRepository;
use Indieinabox\Services\Contracts\MicrosubFilterServiceInterface;

/**
 * Service managing rule evaluation and content filtering for Microsub feeds and timelines.
 */
class MicrosubFilterService implements MicrosubFilterServiceInterface
{
    private MicrosubRepositoryInterface $repository;

    public function __construct(?MicrosubRepositoryInterface $repository = null)
    {
        if ($repository !== null) {
            $this->repository = $repository;
        } elseif (class_exists(Container::class) && Container::getInstance()->has(MicrosubRepositoryInterface::class)) {
            $this->repository = Container::getInstance()->get(MicrosubRepositoryInterface::class);
        } else {
            $this->repository = new SqliteMicrosubRepository();
        }
    }

    /**
     * Evaluates a feed entry against active filtering rules for a given channel.
     * Returns 'drop', 'mute', 'auto_archive', or null.
     */
    #[Override]
    public function evaluate(array $entry, string $channelUid = '*'): ?string
    {
        $filters = $this->getFilters($channelUid);
        if (empty($filters)) {
            return null;
        }

        $matchedActions = [];

        foreach ($filters as $filter) {
            if ($this->matchesFilter($filter, $entry)) {
                $matchedActions[] = $filter['action'];
            }
        }

        if (empty($matchedActions)) {
            return null;
        }

        // Action precedence: drop > mute > auto_archive
        if (in_array('drop', $matchedActions, true)) {
            return 'drop';
        }
        if (in_array('mute', $matchedActions, true)) {
            return 'mute';
        }
        if (in_array('auto_archive', $matchedActions, true)) {
            return 'auto_archive';
        }

        return $matchedActions[0];
    }

    /**
     * Checks whether an entry matches a specific filter rule.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $entry
     * @return bool
     */
    public function matchesFilter(array $filter, array $entry): bool
    {
        $type = (string) ($filter['filter_type'] ?? '');
        $target = trim((string) ($filter['content'] ?? ''));

        if ($target === '') {
            return false;
        }

        return match ($type) {
            'keyword' => $this->matchesKeyword($target, $entry),
            'author' => $this->matchesAuthor($target, $entry),
            'domain' => $this->matchesDomain($target, $entry),
            default => false,
        };
    }

    private function matchesKeyword(string $keyword, array $entry): bool
    {
        $texts = [];

        if (!empty($entry['name']) && is_string($entry['name'])) {
            $texts[] = $entry['name'];
        }
        if (!empty($entry['summary']) && is_string($entry['summary'])) {
            $texts[] = $entry['summary'];
        }
        if (!empty($entry['content'])) {
            if (is_array($entry['content'])) {
                if (!empty($entry['content']['text']) && is_string($entry['content']['text'])) {
                    $texts[] = $entry['content']['text'];
                } elseif (!empty($entry['content']['html']) && is_string($entry['content']['html'])) {
                    $texts[] = strip_tags($entry['content']['html']);
                }
            } elseif (is_string($entry['content'])) {
                $texts[] = strip_tags($entry['content']);
            }
        }

        $haystack = mb_strtolower(implode(' ', $texts));
        return mb_stripos($haystack, mb_strtolower($keyword)) !== false;
    }

    private function matchesAuthor(string $targetAuthor, array $entry): bool
    {
        $targetLower = mb_strtolower($targetAuthor);
        $candidates = [];

        if (!empty($entry['author']) && is_array($entry['author'])) {
            if (!empty($entry['author']['name']) && is_string($entry['author']['name'])) {
                $candidates[] = $entry['author']['name'];
            }
            if (!empty($entry['author']['url']) && is_string($entry['author']['url'])) {
                $candidates[] = $entry['author']['url'];
            }
            if (!empty($entry['author']['nickname']) && is_string($entry['author']['nickname'])) {
                $candidates[] = $entry['author']['nickname'];
            }
        }

        if (!empty($entry['author_name']) && is_string($entry['author_name'])) {
            $candidates[] = $entry['author_name'];
        }
        if (!empty($entry['author_url']) && is_string($entry['author_url'])) {
            $candidates[] = $entry['author_url'];
        }

        foreach ($candidates as $candidate) {
            $candidateLower = mb_strtolower($candidate);
            if ($candidateLower === $targetLower || mb_stripos($candidateLower, $targetLower) !== false) {
                return true;
            }
        }

        return false;
    }

    private function matchesDomain(string $targetDomain, array $entry): bool
    {
        $targetHost = $this->normalizeHost($targetDomain);
        if ($targetHost === '') {
            return false;
        }

        $urlCandidates = [];

        if (!empty($entry['url']) && is_string($entry['url'])) {
            $urlCandidates[] = $entry['url'];
        }
        if (!empty($entry['author']) && is_array($entry['author']) && !empty($entry['author']['url']) && is_string($entry['author']['url'])) {
            $urlCandidates[] = $entry['author']['url'];
        }
        if (!empty($entry['author_url']) && is_string($entry['author_url'])) {
            $urlCandidates[] = $entry['author_url'];
        }
        if (!empty($entry['_indieinabox']) && is_array($entry['_indieinabox'])) {
            if (!empty($entry['_indieinabox']['origin_server']) && is_string($entry['_indieinabox']['origin_server'])) {
                $urlCandidates[] = $entry['_indieinabox']['origin_server'];
            }
            if (!empty($entry['_indieinabox']['feed_url']) && is_string($entry['_indieinabox']['feed_url'])) {
                $urlCandidates[] = $entry['_indieinabox']['feed_url'];
            }
        }

        foreach ($urlCandidates as $url) {
            $candidateHost = $this->normalizeHost($url);
            if ($candidateHost === '') {
                continue;
            }

            if ($candidateHost === $targetHost || str_ends_with($candidateHost, '.' . $targetHost)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHost(string $input): string
    {
        $inputTrimmed = trim($input);
        if (!str_contains($inputTrimmed, '://')) {
            $inputTrimmed = 'http://' . $inputTrimmed;
        }

        $host = parse_url($inputTrimmed, PHP_URL_HOST);
        if (!$host || !is_string($host)) {
            $host = $input;
        }

        $host = strtolower(trim((string) $host));
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    #[Override]
    public function shouldMute(array $entry, string $channelUid = '*'): bool
    {
        return $this->evaluate($entry, $channelUid) === 'mute';
    }

    #[Override]
    public function shouldAutoArchive(array $entry, string $channelUid = '*'): bool
    {
        return $this->evaluate($entry, $channelUid) === 'auto_archive';
    }

    #[Override]
    public function shouldDrop(array $entry, string $channelUid = '*'): bool
    {
        return $this->evaluate($entry, $channelUid) === 'drop';
    }

    #[Override]
    public function getFilters(?string $channelUid = null): array
    {
        return $this->repository->getFilters($channelUid);
    }

    #[Override]
    public function addFilter(string $filterType, string $content, string $action = 'mute', string $channelUid = '*'): int
    {
        return $this->repository->addFilter($filterType, $content, $action, $channelUid);
    }

    #[Override]
    public function deleteFilter(int $id): bool
    {
        return $this->repository->deleteFilter($id);
    }
}
