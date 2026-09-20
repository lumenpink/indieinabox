<?php

declare(strict_types=1);

namespace Indieinabox\Repositories;

use Indieinabox\Core\Database;
use Indieinabox\Repositories\Contracts\ContentRepositoryInterface;
use Indieinabox\Site\Site;
use Indieinabox\Support\Yaml;

/**
 * Filesystem-backed implementation of ContentRepositoryInterface.
 */
class FileSystemContentRepository implements ContentRepositoryInterface
{
    private string $contentBaseDir;
    private string $defaultLang;

    public function __construct(?string $contentBaseDir = null, ?Site $site = null)
    {
        $this->defaultLang = $site?->localization->defaultLang ?? 'en';

        if ($contentBaseDir !== null && $contentBaseDir !== '') {
            $this->contentBaseDir = rtrim($contentBaseDir, DIRECTORY_SEPARATOR);
        } elseif ($site !== null) {
            $base = rtrim($site->paths->baseDir, DIRECTORY_SEPARATOR);
            $contentDir = $site->paths->contentDir;
            $this->contentBaseDir = str_starts_with($contentDir, DIRECTORY_SEPARATOR)
                ? $contentDir
                : $base . DIRECTORY_SEPARATOR . $contentDir;
        } elseif (!empty(Database::$dataDir)) {
            $this->contentBaseDir = dirname(Database::$dataDir) . DIRECTORY_SEPARATOR . 'content';
        } else {
            $this->contentBaseDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'content';
        }
    }

    public function resolveTargetDirectory(
        string $kind,
        ?string $lang = null,
        ?string $year = null,
        ?string $month = null
    ): string {
        $dir = $this->contentBaseDir;

        if ($lang !== null && $lang !== '' && $lang !== $this->defaultLang) {
            $dir .= DIRECTORY_SEPARATOR . $lang;
        }

        if ($kind !== '') {
            $dir .= DIRECTORY_SEPARATOR . $kind;
        }

        if ($year !== null && $month !== null) {
            $dir .= DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;
        }

        return $dir;
    }

    #[\Override]
    public function generateUniqueSlug(
        string $kind,
        string $baseSlug,
        ?string $lang = null,
        ?string $year = null,
        ?string $month = null
    ): string {
        $dir = $this->resolveTargetDirectory($kind, $lang, $year, $month);
        $slug = $baseSlug;
        $counter = 1;

        while (file_exists($dir . DIRECTORY_SEPARATOR . $slug . '.md')) {
            if (is_numeric($baseSlug)) {
                $slug = (string) ((int) $baseSlug + $counter);
            } else {
                $slug = $baseSlug . '-' . $counter;
            }
            $counter++;
        }

        return $slug;
    }

    #[\Override]
    public function buildFrontmatterMarkdown(array $frontmatter, string $body): string
    {
        if (empty($frontmatter)) {
            return $body;
        }

        $yaml = "---\n";
        foreach ($frontmatter as $k => $v) {
            if (is_array($v)) {
                $yaml .= "$k:\n";
                foreach ($v as $item) {
                    $yaml .= "  - $item\n";
                }
            } elseif (is_bool($v)) {
                $yaml .= "$k: " . ($v ? 'true' : 'false') . "\n";
            } elseif (is_numeric($v)) {
                $yaml .= "$k: $v\n";
            } else {
                $yaml .= "$k: \"$v\"\n";
            }
        }
        $yaml .= "---\n\n";

        return $yaml . ltrim($body);
    }

    #[\Override]
    public function parseFrontmatterMarkdown(string $rawContent): array
    {
        if (preg_match('/^---\r?\n(.*?)\r?\n---\r?\n(.*)$/s', $rawContent, $matches)) {
            $yaml = new Yaml();
            $parsed = $yaml->loadString($matches[1]);
            return [
                'frontmatter' => is_array($parsed) ? $parsed : [],
                'body' => $matches[2],
            ];
        }

        return [
            'frontmatter' => [],
            'body' => $rawContent,
        ];
    }

    #[\Override]
    public function save(
        string $kind,
        string $slug,
        string $content,
        array $frontmatter = [],
        ?string $lang = null,
        ?string $year = null,
        ?string $month = null
    ): string {
        $dir = $this->resolveTargetDirectory($kind, $lang, $year, $month);

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $fullContent = $this->buildFrontmatterMarkdown($frontmatter, $content);
        $filepath = $dir . DIRECTORY_SEPARATOR . $slug . '.md';

        file_put_contents($filepath, $fullContent);
        return $filepath;
    }

    #[\Override]
    public function findByPath(string $filepath): ?string
    {
        if (!file_exists($filepath)) {
            return null;
        }

        $content = @file_get_contents($filepath);
        return $content !== false ? $content : null;
    }

    #[\Override]
    public function delete(string $filepath): bool
    {
        if (file_exists($filepath)) {
            return @unlink($filepath);
        }
        return false;
    }

    #[\Override]
    public function exists(string $filepath): bool
    {
        return file_exists($filepath);
    }

    #[\Override]
    public function scan(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $entries = scandir($dir);
        if ($entries === false) {
            return [];
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path) && str_ends_with(strtolower($entry), '.md')) {
                $files[] = $path;
            } elseif (is_dir($path)) {
                $files = array_merge($files, $this->scan($path));
            }
        }

        return $files;
    }

    /**
     * @param \Indieinabox\Specifications\Contracts\SpecificationInterface $specification
     * @param string|null $dir
     * @return array<int, array{filepath: string, frontmatter: array<string, mixed>, body: string, slug: string, kind: string, date: string|null, lang: string|null}>
     */
    #[\Override]
    public function query(\Indieinabox\Specifications\Contracts\SpecificationInterface $specification, ?string $dir = null): array
    {
        $searchDir = $dir !== null && $dir !== '' ? $dir : $this->contentBaseDir;
        $files = $this->scan($searchDir);
        $results = [];

        foreach ($files as $file) {
            $raw = $this->findByPath($file);
            if ($raw === null) {
                continue;
            }

            $parsed = $this->parseFrontmatterMarkdown($raw);
            $frontmatter = $parsed['frontmatter'];
            $body = $parsed['body'];

            // Extract relative parts
            $rel = ltrim(substr($file, strlen($searchDir)), DIRECTORY_SEPARATOR);
            $parts = explode(DIRECTORY_SEPARATOR, $rel);

            $slug = basename($file, '.md');
            $kind = (string) ($frontmatter['kind'] ?? '');
            $lang = isset($frontmatter['lang']) ? (string) $frontmatter['lang'] : (isset($frontmatter['language']) ? (string) $frontmatter['language'] : null);

            if ($kind === '' && count($parts) >= 2) {
                if (strlen($parts[0]) === 2 && count($parts) >= 3) {
                    $lang = $lang ?? $parts[0];
                    $kind = $parts[1];
                } else {
                    $kind = $parts[0];
                }
            }

            $date = isset($frontmatter['date']) ? (string) $frontmatter['date'] : null;

            $candidate = [
                'filepath' => $file,
                'frontmatter' => $frontmatter,
                'body' => $body,
                'slug' => (string) $slug,
                'kind' => (string) $kind,
                'date' => $date,
                'lang' => $lang,
            ];

            if ($specification->isSatisfiedBy($candidate)) {
                $results[] = $candidate;
            }
        }

        return $results;
    }
}
