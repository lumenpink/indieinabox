<?php

declare(strict_types=1);

namespace Indieinabox;

/**
 * Class ModerationHandler
 * Handles comment moderation interface and actions
 */
class ModerationHandler
{
    /**
     * @var \Indieinabox\Site
     */
    private Site $site;

    /**
     * Method __construct
     * @param \Indieinabox\Site $site
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Method handle
     * @return void
     */
    public function handle(): void
    {
        // Require authentication
        if (empty($_SESSION['admin_authenticated'])) {
            $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
            header('Location: ' . $fqdn . '/admin/config');
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->handleAction();
            return;
        }

        $this->renderInterface();
    }

    /**
     * Method handleAction
     * @return void
     */
    private function handleAction(): void
    {
        $action = $_POST['action'] ?? '';
        $id = $_POST['id'] ?? '';
        
        if ($action && $id) {
            $dataDir = \Indieinabox\Database::$dataDir ?? (dirname(__DIR__) . '/data');
            $notificationsDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'notifications';
            $filePath = $notificationsDir . DIRECTORY_SEPARATOR . $id . '.md';
            
            if (file_exists($filePath)) {
                if ($action === 'delete') {
                    unlink($filePath);
                } elseif ($action === 'approve') {
                    $content = file_get_contents($filePath);
                    if ($content) {
                        $yaml = new \Indieinabox\Yaml();
                        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
                            $parsed = $yaml->loadString($matches[1]);
                            $body = trim($matches[2]);
                            $meta = $parsed['metadata'] ?? $parsed;
                            
                            $meta['status'] = 'approved';
                            
                            // Keep 'metadata' wrapping if it had it, else write plain array
                            $newYaml = isset($parsed['metadata']) ? ['metadata' => $meta, 'content' => $parsed['content'] ?? ''] : $meta;
                            $yamlStr = $yaml->dump($newYaml);
                            
                            $newContent = "---\n" . trim($yamlStr) . "\n---\n\n" . $body;
                            file_put_contents($filePath, $newContent);
                        } else {
                            // No body, just yaml
                            $parsed = $yaml->loadString($content);
                            $meta = $parsed['metadata'] ?? $parsed;
                            $meta['status'] = 'approved';
                            $newYaml = isset($parsed['metadata']) ? ['metadata' => $meta, 'content' => $parsed['content'] ?? ''] : $meta;
                            $yamlStr = $yaml->dump($newYaml);
                            $newContent = "---\n" . trim($yamlStr) . "\n---";
                            file_put_contents($filePath, $newContent);
                        }
                    }
                }
            }
        }
        
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        header('Location: ' . $fqdn . '/admin/moderation');
    }

    /**
     * Method renderInterface
     * @return void
     */
    private function renderInterface(): void
    {
        $dataDir = \Indieinabox\Database::$dataDir ?? (dirname(__DIR__) . '/data');
        $notificationsDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'notifications';
        
        $pending = [];
        
        if (is_dir($notificationsDir)) {
            $iter = new \DirectoryIterator($notificationsDir);
            foreach ($iter as $file) {
                if ($file->isFile() && $file->getExtension() === 'md') {
                    $content = file_get_contents($file->getPathname());
                    if ($content) {
                        $yaml = new \Indieinabox\Yaml();
                        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
                            $parsed = $yaml->loadString($matches[1]);
                            $body = trim($matches[2]);
                        } else {
                            $parsed = $yaml->loadString($content);
                            $body = '';
                        }
                        
                        $meta = $parsed['metadata'] ?? $parsed;
                        $status = $meta['status'] ?? 'approved'; // Assumed approved if missing
                        
                        if ($status === 'pending') {
                            $meta['id_filename'] = $file->getBasename('.md');
                            $meta['body'] = $body;
                            $pending[] = $meta;
                        }
                    }
                }
            }
        }
        
        // Sort newest first based on published timestamp if available, else by file mtime
        usort($pending, function($a, $b) use ($notificationsDir) {
            $timeA = $a['published'] ?? filemtime($notificationsDir . '/' . $a['id_filename'] . '.md');
            $timeB = $b['published'] ?? filemtime($notificationsDir . '/' . $b['id_filename'] . '.md');
            return $timeB <=> $timeA;
        });

        // Try to use the unified admin layout if it exists
        $adminLayoutPath = dirname(__DIR__) . '/resources/views/admin_layout.php';
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        
        $activeTab = 'moderation';
        
        // Start output buffering for the main content
        ob_start();
        ?>
        <div style="padding: 2em; font-family: sans-serif;">
            <h2>Comment Moderation</h2>
            <?php if (empty($pending)): ?>
                <p>No pending comments to moderate.</p>
            <?php else: ?>
                <?php foreach ($pending as $item): ?>
                    <div style="border: 1px solid #ccc; margin-bottom: 1em; padding: 1em; background: rgba(0,0,0,0.02);">
                        <div style="margin-bottom: 0.5em;">
                            <strong><?= htmlspecialchars($item['author_name'] ?? 'Anonymous') ?></strong> 
                            (<?= htmlspecialchars($item['interaction_type'] ?? 'interaction') ?>) 
                            on <a href="<?= htmlspecialchars($item['target'] ?? '#') ?>"><?= htmlspecialchars($item['target_hash'] ?? 'Target') ?></a>
                        </div>
                        <?php if (!empty($item['url'])): ?>
                            <div style="font-size: 0.9em; margin-bottom: 0.5em;">
                                <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank"><?= htmlspecialchars($item['url']) ?></a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($item['body']) || !empty($item['content'])): ?>
                            <div style="background: rgba(255,255,255,0.5); padding: 1em; border-left: 4px solid #ccc; margin: 1em 0; white-space: pre-wrap;"><?= htmlspecialchars($item['body'] ?: ($item['content'] ?? '')) ?></div>
                        <?php endif; ?>
                        
                        <div style="display: flex; gap: 10px; margin-top: 1em;">
                            <form method="POST" action="<?= $fqdn ?>/admin/moderation">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($item['id_filename']) ?>">
                                <button type="submit" style="background: #28a745; color: white; border: none; padding: 8px 16px; cursor: pointer;">Approve</button>
                            </form>
                            <form method="POST" action="<?= $fqdn ?>/admin/moderation" onsubmit="return confirm('Are you sure you want to delete this interaction?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($item['id_filename']) ?>">
                                <button type="submit" style="background: #dc3545; color: white; border: none; padding: 8px 16px; cursor: pointer;">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        $content = ob_get_clean();
        
        if (file_exists($adminLayoutPath)) {
            // Include admin layout, which expects $activeTab and $content to be set
            include $adminLayoutPath;
        } else {
            // Fallback if admin layout is not yet created
            echo $content;
        }
    }
}
