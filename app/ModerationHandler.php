<?php

declare(strict_types=1);

namespace Indieinabox;

/**
 * Class ModerationHandler
 * 
 * Provides the admin interface and logic for moderating incoming comments, 
 * webmentions, and other interactions (e.g., pending or spam).
 */
class ModerationHandler
{
    /**
     * @var \Indieinabox\Site
     */
    private Site $site;

    /**
     * Initializes the moderation handler with the site configuration context.
     *
     * @param \Indieinabox\Site $site The site configuration object.
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Main entry point for the moderation panel.
     * Enforces admin authentication and routes to either action handling (POST)
     * or rendering the interface (GET).
     *
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
     * Processes moderation form actions (approve, delete).
     * Moves or modifies the YAML/Markdown files in the notifications or spam directories.
     *
     * @return void
     */
    private function handleAction(): void
    {
        $action = $_POST['action'] ?? '';
        $id = $_POST['id'] ?? '';
        $type = $_POST['type'] ?? 'pending';
        
        if ($action && $id) {
            $dataDir = \Indieinabox\Database::$dataDir ?? (dirname(__DIR__) . '/data');
            $notificationsDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'notifications';
            $spamDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'spam';
            $sourceDir = $type === 'spam' ? $spamDir : $notificationsDir;
            $filePath = $sourceDir . DIRECTORY_SEPARATOR . $id . '.md';
            
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
                        } else {
                            // No body, just yaml
                            $parsed = $yaml->loadString($content);
                            $meta = $parsed['metadata'] ?? $parsed;
                            $meta['status'] = 'approved';
                            $newYaml = isset($parsed['metadata']) ? ['metadata' => $meta, 'content' => $parsed['content'] ?? ''] : $meta;
                            $yamlStr = $yaml->dump($newYaml);
                            $newContent = "---\n" . trim($yamlStr) . "\n---";
                        }
                        
                        $targetPath = $type === 'spam' ? $notificationsDir . DIRECTORY_SEPARATOR . $id . '.md' : $filePath;
                        if ($type === 'spam' && !is_dir($notificationsDir)) {
                            @mkdir($notificationsDir, 0755, true);
                        }
                        file_put_contents($targetPath, $newContent);
                        if ($type === 'spam' && $targetPath !== $filePath) {
                            unlink($filePath);
                        }
                    }
                }
            }
        }
        
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        header('Location: ' . $fqdn . '/admin/moderation');
    }

    /**
     * Renders the moderation UI.
     * Scans the notification and spam directories, parses the pending files, 
     * sorts them by date, and outputs the HTML layout for the admin to review.
     *
     * @return void
     */
    private function renderInterface(): void
    {
        $dataDir = \Indieinabox\Database::$dataDir ?? (dirname(__DIR__) . '/data');
        $notificationsDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'notifications';
        $spamDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'spam';
        
        $pending = [];
        $spam = [];
        
        $processDir = function(string $dir, array &$list, string $targetStatus) {
            if (is_dir($dir)) {
                $iter = new \DirectoryIterator($dir);
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
                            $status = $meta['status'] ?? 'approved';
                            
                            if ($status === $targetStatus) {
                                $meta['id_filename'] = $file->getBasename('.md');
                                $meta['body'] = $body;
                                $list[] = $meta;
                            }
                        }
                    }
                }
            }
        };

        $processDir($notificationsDir, $pending, 'pending');
        $processDir($spamDir, $spam, 'spam');
        
        $sortFn = function($a, $b) use ($notificationsDir, $spamDir) {
            $dirA = ($a['status'] ?? 'pending') === 'spam' ? $spamDir : $notificationsDir;
            $dirB = ($b['status'] ?? 'pending') === 'spam' ? $spamDir : $notificationsDir;
            $timeA = $a['published'] ?? filemtime($dirA . '/' . $a['id_filename'] . '.md');
            $timeB = $b['published'] ?? filemtime($dirB . '/' . $b['id_filename'] . '.md');
            return $timeB <=> $timeA;
        };

        usort($pending, $sortFn);
        usort($spam, $sortFn);

        // Try to use the unified admin layout if it exists
        $adminLayoutPath = dirname(__DIR__) . '/resources/views/admin_layout.php';
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        
        $activeTab = 'moderation';
        
        // Start output buffering for the main content
        ob_start();
        ?>
        <div style="padding: 2em; font-family: sans-serif;">
            <h2>Comment Moderation</h2>
            
            <h3>Pending Comments</h3>
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
                                <input type="hidden" name="type" value="pending">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($item['id_filename']) ?>">
                                <button type="submit" style="background: #28a745; color: white; border: none; padding: 8px 16px; cursor: pointer;">Approve</button>
                            </form>
                            <form method="POST" action="<?= $fqdn ?>/admin/moderation" onsubmit="return confirm('Are you sure you want to delete this interaction?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="type" value="pending">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($item['id_filename']) ?>">
                                <button type="submit" style="background: #dc3545; color: white; border: none; padding: 8px 16px; cursor: pointer;">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <h3 style="margin-top: 2em; color: #dc3545;">Spam Folder (Akismet)</h3>
            <?php if (empty($spam)): ?>
                <p>No spam comments found.</p>
            <?php else: ?>
                <?php foreach ($spam as $item): ?>
                    <div style="border: 1px solid #dc3545; margin-bottom: 1em; padding: 1em; background: rgba(220,53,69,0.05);">
                        <div style="margin-bottom: 0.5em; color: #dc3545; font-weight: bold;">[FLAGGED AS SPAM]</div>
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
                            <div style="background: rgba(255,255,255,0.5); padding: 1em; border-left: 4px solid #dc3545; margin: 1em 0; white-space: pre-wrap; opacity: 0.7;"><?= htmlspecialchars($item['body'] ?: ($item['content'] ?? '')) ?></div>
                        <?php endif; ?>
                        
                        <div style="display: flex; gap: 10px; margin-top: 1em;">
                            <form method="POST" action="<?= $fqdn ?>/admin/moderation" onsubmit="return confirm('Are you sure you want to approve this spam?');">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="type" value="spam">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($item['id_filename']) ?>">
                                <button type="submit" style="background: #ffc107; color: #000; border: none; padding: 8px 16px; cursor: pointer;">Not Spam (Approve)</button>
                            </form>
                            <form method="POST" action="<?= $fqdn ?>/admin/moderation" onsubmit="return confirm('Are you sure you want to delete this spam?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="type" value="spam">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($item['id_filename']) ?>">
                                <button type="submit" style="background: #dc3545; color: white; border: none; padding: 8px 16px; cursor: pointer;">Delete Permanently</button>
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
