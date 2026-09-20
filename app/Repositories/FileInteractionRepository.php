<?php

declare(strict_types=1);

namespace Indieinabox\Repositories;

use DirectoryIterator;
use Indieinabox\Core\Database;
use Indieinabox\Support\Yaml;
use Indieinabox\Repositories\Contracts\InteractionRepositoryInterface;

/**
 * File-backed repository storing interactions in channel markdown files.
 */
class FileInteractionRepository implements InteractionRepositoryInterface
{
    private ?string $customDataDir;
    private Yaml $yaml;

    public function __construct(?string $dataDir = null, ?Yaml $yaml = null)
    {
        $this->customDataDir = $dataDir;
        $this->yaml = $yaml ?? new Yaml();
    }

    public function getDataDir(): string
    {
        if ($this->customDataDir !== null) {
            return $this->customDataDir;
        }
        return (Database::$dataDir !== '' && Database::$dataDir !== null)
            ? Database::$dataDir
            : (dirname(__DIR__, 2) . '/data');
    }

    private function getNotificationsDir(): string
    {
        $dir = $this->getDataDir() . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'notifications';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function getSpamDir(): string
    {
        $dir = $this->getDataDir() . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'spam';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function getInboxDir(): string
    {
        $dir = $this->getDataDir() . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'inbox';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    #[\Override]
    public function findByPageSlug(string $slug, ?string $type = null): array
    {
        $hash = md5($slug);
        $notificationsDir = $this->getNotificationsDir();
        $interactions = [];

        if (is_dir($notificationsDir)) {
            $iter = new DirectoryIterator($notificationsDir);
            foreach ($iter as $file) {
                if ($file->isFile() && $file->getExtension() === 'md') {
                    if (str_starts_with($file->getFilename(), $hash . '_')) {
                        $content = file_get_contents($file->getPathname());
                        if ($content) {
                            if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
                                $parsed = $this->yaml->loadString($matches[1]);
                                $body = trim($matches[2]);
                            } else {
                                $parsed = $this->yaml->loadString($content);
                                $body = '';
                            }

                            if (isset($parsed['metadata'])) {
                                $meta = $parsed['metadata'];
                                $meta['interaction_content'] = $parsed['content'] ?? $body;
                            } else {
                                $meta = $parsed;
                                $meta['interaction_content'] = $meta['content'] ?? $body;
                            }

                            $status = $meta['status'] ?? 'approved';
                            if ($status !== 'approved') {
                                continue;
                            }

                            $interactionType = $meta['interaction_type'] ?? 'webmention';
                            if ($type === null || $interactionType === $type) {
                                $interactions[] = $meta;
                            }
                        }
                    }
                }
            }
        }

        return $interactions;
    }

    #[\Override]
    public function listByStatus(string $status = 'pending'): array
    {
        $dir = $status === 'spam' ? $this->getSpamDir() : $this->getNotificationsDir();
        if (!is_dir($dir)) {
            return [];
        }

        $items = [];
        $files = scandir($dir) ?: [];
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || !str_ends_with($f, '.md')) {
                continue;
            }

            $id = substr($f, 0, -3);
            $content = (string)file_get_contents($dir . DIRECTORY_SEPARATOR . $f);

            $meta = [];
            $body = '';
            if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
                $meta = $this->yaml->loadString($matches[1]);
                $body = trim($matches[2]);
            }

            $currentStatus = $meta['status'] ?? 'pending';
            if ($status === 'approved' && $currentStatus !== 'approved') {
                continue;
            }
            if ($status === 'pending' && $currentStatus === 'approved') {
                continue;
            }

            $items[] = [
                'id' => $id,
                'status' => $currentStatus,
                'meta' => $meta,
                'body' => $body,
                'file' => $dir . DIRECTORY_SEPARATOR . $f,
                'source' => $meta['source'] ?? ($meta['url'] ?? ''),
                'target' => $meta['target'] ?? '',
                'author_name' => $meta['author_name'] ?? ($meta['author']['name'] ?? 'Unknown'),
                'author_photo' => $meta['author_photo'] ?? ($meta['author']['photo'] ?? ''),
                'interaction_type' => $meta['interaction_type'] ?? 'webmention',
                'published' => $meta['published'] ?? time(),
                'content' => $body ?: ($meta['content'] ?? ''),
            ];
        }

        return $items;
    }

    #[\Override]
    public function updateStatus(string $id, string $status, string $type = 'pending'): bool
    {
        $notificationsDir = $this->getNotificationsDir();
        $spamDir = $this->getSpamDir();
        $sourceDir = ($type === 'spam') ? $spamDir : $notificationsDir;
        $srcPath = $sourceDir . DIRECTORY_SEPARATOR . $id . '.md';

        if (!file_exists($srcPath)) {
            $fallbackDir = ($type === 'spam') ? $notificationsDir : $spamDir;
            $fallbackPath = $fallbackDir . DIRECTORY_SEPARATOR . $id . '.md';
            if (file_exists($fallbackPath)) {
                $srcPath = $fallbackPath;
            } else {
                return false;
            }
        }

        $content = (string)file_get_contents($srcPath);
        if ($content === '') {
            return false;
        }

        $destDir = ($status === 'spam') ? $spamDir : $notificationsDir;
        $destPath = $destDir . DIRECTORY_SEPARATOR . $id . '.md';

        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $parsed = $this->yaml->loadString($matches[1]);
            $body = trim($matches[2]);
            $meta = $parsed['metadata'] ?? $parsed;
            $meta['status'] = $status;

            $newYaml = isset($parsed['metadata']) ? ['metadata' => $meta, 'content' => $parsed['content'] ?? ''] : $meta;
            $yamlStr = $this->yaml->dump($newYaml);
            $newContent = "---\n" . trim($yamlStr) . "\n---\n\n" . $body;
        } else {
            $parsed = $this->yaml->loadString($content);
            $meta = $parsed['metadata'] ?? $parsed;
            $meta['status'] = $status;

            $newYaml = isset($parsed['metadata']) ? ['metadata' => $meta, 'content' => $parsed['content'] ?? ''] : $meta;
            $yamlStr = $this->yaml->dump($newYaml);
            $newContent = "---\n" . trim($yamlStr) . "\n---";
        }

        file_put_contents($destPath, $newContent);
        if ($destPath !== $srcPath && file_exists($srcPath)) {
            @unlink($srcPath);
        }

        return true;
    }

    #[\Override]
    public function save(string $id, array $metadata, string $content = '', string $channel = 'notifications'): bool
    {
        $dir = match ($channel) {
            'spam' => $this->getSpamDir(),
            'inbox' => $this->getInboxDir(),
            default => $this->getNotificationsDir(),
        };
        $filePath = $dir . DIRECTORY_SEPARATOR . $id . '.md';

        $yamlStr = $this->yaml->dump($metadata);
        $fileContent = "---\n" . $yamlStr . "---\n\n" . $content;

        return file_put_contents($filePath, $fileContent) !== false;
    }

    #[\Override]
    public function delete(string $id, string $type = 'pending'): bool
    {
        $dir = $type === 'spam' ? $this->getSpamDir() : $this->getNotificationsDir();
        $filePath = $dir . DIRECTORY_SEPARATOR . $id . '.md';

        if (file_exists($filePath)) {
            return @unlink($filePath);
        }

        return false;
    }
}
