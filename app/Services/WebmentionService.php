<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Core\Database;
use Indieinabox\Site;
use Indieinabox\Webmention\SourceVerifier;
use PDO;

/**
 * Service orchestrating incoming and outgoing Webmentions, verification, and persistence.
 */
class WebmentionService
{
    private PDO $db;
    private SourceVerifier $sourceVerifier;

    public function __construct(?PDO $db = null, ?SourceVerifier $sourceVerifier = null)
    {
        $this->db = $db ?? Database::getDb();
        $this->sourceVerifier = $sourceVerifier ?? new SourceVerifier();
    }

    /**
     * Enqueues an incoming webmention for asynchronous background processing.
     */
    public function queue(string $source, string $target): bool
    {
        $payload = [
            'source' => $source,
            'target' => $target,
        ];

        $stmt = $this->db->prepare("INSERT INTO inbox_queue (type, payload_json, created_at) VALUES (?, ?, ?)");
        return $stmt->execute(['webmention', json_encode($payload), time()]);
    }

    /**
     * Verifies that the source URL contains a valid link back to the target.
     *
     * @return array{success: bool, message?: string, content?: array{title: string, text: string, whostyle?: array<array-key, mixed>|null}}
     */
    public function verifySourceLink(string $source, string $target): array
    {
        return $this->sourceVerifier->verifySourceLink($source, $target);
    }

    /**
     * Validates whether a target URL belongs to this site and resolves to an existing page file.
     */
    public function isValidTarget(string $target, Site $site): bool
    {
        $targetHost = parse_url($target, PHP_URL_HOST);
        $siteHost = parse_url($site->metadata->fqdn ?? '', PHP_URL_HOST);

        if (empty($targetHost) || empty($siteHost) || strcasecmp($targetHost, $siteHost) !== 0) {
            return false;
        }

        $targetPath = parse_url($target, PHP_URL_PATH) ?? '/';
        $sitePath = parse_url($site->metadata->fqdn ?? '', PHP_URL_PATH);
        if ($sitePath && $sitePath !== '/' && str_starts_with($targetPath, $sitePath)) {
            $targetPath = substr($targetPath, strlen($sitePath));
        }

        $base = rtrim($site->paths->baseDir, DIRECTORY_SEPARATOR);
        $outputDir = $site->paths->outputDirHtml;

        $targetPathClean = str_replace('..', '', urldecode($targetPath));
        if ($targetPathClean === '' || $targetPathClean === '/') {
            $targetFile = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . 'index.html';
        } else {
            $targetFile = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . trim($targetPathClean, '/') . DIRECTORY_SEPARATOR . 'index.html';
            if (!file_exists($targetFile)) {
                $targetFile = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . trim($targetPathClean, '/');
            }
        }

        return file_exists($targetFile);
    }

    /**
     * Retrieves stored webmentions for a given page slug.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMentions(string $slug): array
    {
        $slugHash = md5(trim($slug, '/'));
        $dataDir = Database::$dataDir !== '' ? Database::$dataDir : dirname(__DIR__, 2) . '/data';
        $file = $dataDir . '/webmentions/' . $slugHash . '.json';

        if (!file_exists($file)) {
            return [];
        }

        $content = (string) file_get_contents($file);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Saves or appends a webmention entry for a given page slug.
     *
     * @param array<string, mixed> $mentionData
     */
    public function saveMention(string $slug, array $mentionData): bool
    {
        $slugHash = md5(trim($slug, '/'));
        $dataDir = Database::$dataDir !== '' ? Database::$dataDir : dirname(__DIR__, 2) . '/data';
        $dir = $dataDir . '/webmentions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $file = $dir . '/' . $slugHash . '.json';
        $existing = $this->getMentions($slug);

        // Deduplicate by source
        $source = $mentionData['source'] ?? '';
        $filtered = array_values(array_filter($existing, fn(array $m): bool => ($m['source'] ?? '') !== $source));
        $filtered[] = $mentionData;

        return file_put_contents($file, json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
    }
}
