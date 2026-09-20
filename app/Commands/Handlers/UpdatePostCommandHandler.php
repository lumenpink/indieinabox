<?php

declare(strict_types=1);

namespace Indieinabox\Commands\Handlers;

use Indieinabox\Commands\UpdatePostCommand;
use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Repositories\Contracts\ContentRepositoryInterface;
use Indieinabox\Repositories\FileSystemContentRepository;
use Indieinabox\Site\Site;

/**
 * Handler for UpdatePostCommand.
 */
class UpdatePostCommandHandler
{
    private ?ContentRepositoryInterface $contentRepo;

    public function __construct(?ContentRepositoryInterface $contentRepo = null)
    {
        $this->contentRepo = $contentRepo;
    }

    /**
     * @param UpdatePostCommand $command
     * @return array{status: int, headers: array<string, string>, error?: string, error_description?: string}
     */
    public function handle(UpdatePostCommand $command): array
    {
        $site = $command->getSite();
        $url = $command->getUrl();
        $contentRepo = $this->contentRepo ?? new FileSystemContentRepository(null, $site);

        $filepath = $this->resolveFilepathFromUrl($site, $url, $contentRepo);
        if ($filepath === null || !$contentRepo->exists($filepath)) {
            return [
                'status' => 404,
                'headers' => [],
                'error' => 'Not Found',
                'error_description' => "Post for URL [{$url}] not found.",
            ];
        }

        $rawContent = (string) $contentRepo->findByPath($filepath);
        $parsed = $contentRepo->parseFrontmatterMarkdown($rawContent);

        $frontmatter = $parsed['frontmatter'];
        $body = $parsed['body'];

        // Apply replace
        foreach ($command->getReplace() as $key => $value) {
            if ($key === 'content') {
                $body = is_array($value) ? (string) ($value['html'] ?? ($value['value'] ?? '')) : (string) $value;
            } else {
                $frontmatter[$key] = $value;
            }
        }

        // Apply add
        foreach ($command->getAdd() as $key => $values) {
            $valuesArray = is_array($values) ? $values : [$values];
            $current = $frontmatter[$key] ?? [];
            if (!is_array($current)) {
                $current = [$current];
            }
            $frontmatter[$key] = array_unique(array_merge($current, $valuesArray));
        }

        // Apply delete
        foreach ($command->getDelete() as $keyOrIndex => $value) {
            if (is_int($keyOrIndex) && is_string($value)) {
                unset($frontmatter[$value]);
            } elseif (is_string($keyOrIndex) && is_array($value)) {
                /** @psalm-suppress InvalidArrayOffset, NoValue */
                if (isset($frontmatter[$keyOrIndex]) && is_array($frontmatter[$keyOrIndex])) {
                    /** @psalm-suppress InvalidArrayOffset */
                    $frontmatter[$keyOrIndex] = array_values(array_diff($frontmatter[$keyOrIndex], $value));
                }
            }
        }

        $newMarkdown = $contentRepo->buildFrontmatterMarkdown($frontmatter, $body);
        file_put_contents($filepath, $newMarkdown);

        // Queue site rebuild
        try {
            $db = Database::getDb();
            if ($db) {
                $stmt = $db->query("SELECT 1 FROM inbox_queue WHERE type = 'build_site'");
                if ($stmt && !$stmt->fetch()) {
                    $insert = $db->prepare('INSERT INTO inbox_queue (type, payload_json, created_at) VALUES (?, ?, ?)');
                    $insert->execute(['build_site', json_encode([]), time()]);
                }
            }
        } catch (\Throwable) {
        }

        return [
            'status' => 200,
            'headers' => ['Location' => $url],
        ];
    }

    private function resolveFilepathFromUrl(Site $site, string $url, ContentRepositoryInterface $contentRepo): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;
        $path = preg_replace('/\.(html|md)$/i', '', $path) ?? '';
        $path = trim($path, '/');

        $base = rtrim($site->paths->baseDir, DIRECTORY_SEPARATOR);
        $contentDir = $site->paths->contentDir;
        $absContentDir = str_starts_with($contentDir, DIRECTORY_SEPARATOR)
            ? $contentDir
            : $base . DIRECTORY_SEPARATOR . $contentDir;

        // Direct check: content/$path.md
        $direct = $absContentDir . DIRECTORY_SEPARATOR . $path . '.md';
        if (file_exists($direct)) {
            return $direct;
        }

        // Search by basename
        $basename = basename($path);
        $scanned = $contentRepo->scan($absContentDir);
        foreach ($scanned as $file) {
            if (basename($file, '.md') === $basename) {
                return $file;
            }
        }

        return null;
    }
}
