<?php

declare(strict_types=1);

namespace Indieinabox\Commands\Handlers;

use Indieinabox\ActivityPub\ActivityBuilder;
use Indieinabox\Commands\DeletePostCommand;
use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Repositories\Contracts\ContentRepositoryInterface;
use Indieinabox\Repositories\FileSystemContentRepository;
use Indieinabox\Services\OutboxService;
use Indieinabox\Site\Site;

/**
 * Handler for DeletePostCommand.
 */
class DeletePostCommandHandler
{
    private ?ContentRepositoryInterface $contentRepo;
    private ?OutboxService $outboxService;

    public function __construct(
        ?ContentRepositoryInterface $contentRepo = null,
        ?OutboxService $outboxService = null
    ) {
        $this->contentRepo = $contentRepo;

        $container = class_exists(Container::class) ? Container::getInstance() : null;

        if ($outboxService !== null) {
            $this->outboxService = $outboxService;
        } elseif ($container && $container->has(OutboxService::class)) {
            $this->outboxService = $container->get(OutboxService::class);
        } else {
            $this->outboxService = null;
        }
    }

    /**
     * @param DeletePostCommand $command
     * @return array{status: int, headers: array<string, string>, error?: string, error_description?: string}
     */
    public function handle(DeletePostCommand $command): array
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

        $contentRepo->delete($filepath);

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

        // ActivityPub federation delete broadcast
        if ($this->outboxService !== null || class_exists(OutboxService::class)) {
            $outbox = $this->outboxService ?? new OutboxService();
            $baseUrl = rtrim($site->fqdn ?? '', '/');
            $actorId = $baseUrl . '/actor';
            $deleteActivity = [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => $url . '#delete',
                'type' => 'Delete',
                'actor' => $actorId,
                'to' => ['https://www.w3.org/ns/activitystreams#Public'],
                'object' => [
                    'id' => $url,
                    'type' => 'Tombstone',
                ],
            ];
            $outbox->broadcastActivity($deleteActivity);
        }

        return [
            'status' => 204,
            'headers' => [],
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
