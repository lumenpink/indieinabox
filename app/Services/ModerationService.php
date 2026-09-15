<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Database;
use Indieinabox\Yaml;

/**
 * Service managing moderation workflows for incoming notifications, comments, and interactions.
 */
class ModerationService
{
    private string $dataDir;
    private Yaml $yaml;

    public function __construct(?string $dataDir = null, ?Yaml $yaml = null)
    {
        $this->dataDir = $dataDir ?? (Database::$dataDir !== '' ? Database::$dataDir : dirname(__DIR__, 2) . '/data');
        $this->yaml = $yaml ?? new Yaml();
    }

    /**
     * Approves a pending or spam notification by setting status to approved and relocating to notifications dir.
     */
    public function approveInteraction(string $id, string $type = 'pending'): bool
    {
        $notificationsDir = $this->dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'notifications';
        $spamDir = $this->dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'spam';
        $sourceDir = $type === 'spam' ? $spamDir : $notificationsDir;
        $filePath = $sourceDir . DIRECTORY_SEPARATOR . $id . '.md';

        if (!file_exists($filePath)) {
            return false;
        }

        $content = (string) file_get_contents($filePath);
        if ($content === '') {
            return false;
        }

        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $parsed = $this->yaml->loadString($matches[1]);
            $body = trim($matches[2]);
            $meta = $parsed['metadata'] ?? $parsed;
            $meta['status'] = 'approved';

            $newYaml = isset($parsed['metadata']) ? ['metadata' => $meta, 'content' => $parsed['content'] ?? ''] : $meta;
            $yamlStr = $this->yaml->dump($newYaml);
            $newContent = "---\n" . trim($yamlStr) . "\n---\n\n" . $body;
        } else {
            $parsed = $this->yaml->loadString($content);
            $meta = $parsed['metadata'] ?? $parsed;
            $meta['status'] = 'approved';

            $newYaml = isset($parsed['metadata']) ? ['metadata' => $meta, 'content' => $parsed['content'] ?? ''] : $meta;
            $yamlStr = $this->yaml->dump($newYaml);
            $newContent = "---\n" . trim($yamlStr) . "\n---";
        }

        $targetPath = $type === 'spam' ? $notificationsDir . DIRECTORY_SEPARATOR . $id . '.md' : $filePath;
        if ($type === 'spam' && !is_dir($notificationsDir)) {
            @mkdir($notificationsDir, 0755, true);
        }

        file_put_contents($targetPath, $newContent);
        if ($type === 'spam' && $targetPath !== $filePath && file_exists($filePath)) {
            unlink($filePath);
        }

        return true;
    }

    /**
     * Deletes an interaction file permanently.
     */
    public function deleteInteraction(string $id, string $type = 'pending'): bool
    {
        $notificationsDir = $this->dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'notifications';
        $spamDir = $this->dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'spam';
        $sourceDir = $type === 'spam' ? $spamDir : $notificationsDir;
        $filePath = $sourceDir . DIRECTORY_SEPARATOR . $id . '.md';

        if (file_exists($filePath)) {
            return unlink($filePath);
        }

        return false;
    }

    /**
     * Lists interactions by type ('pending', 'approved', or 'spam').
     *
     * @return array<int, array<string, mixed>>
     */
    public function listInteractions(string $type = 'pending'): array
    {
        $notificationsDir = $this->dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'notifications';
        $spamDir = $this->dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'spam';
        $dir = $type === 'spam' ? $spamDir : $notificationsDir;

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
            $content = (string) file_get_contents($dir . DIRECTORY_SEPARATOR . $f);

            $meta = [];
            $body = '';
            if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
                $meta = $this->yaml->loadString($matches[1]);
                $body = trim($matches[2]);
            }

            $status = $meta['status'] ?? 'pending';
            if ($type === 'approved' && $status !== 'approved') {
                continue;
            }
            if ($type === 'pending' && $status === 'approved') {
                continue;
            }

            $items[] = [
                'id' => $id,
                'status' => $status,
                'meta' => $meta,
                'body' => $body,
                'file' => $dir . DIRECTORY_SEPARATOR . $f,
            ];
        }

        return $items;
    }
}
