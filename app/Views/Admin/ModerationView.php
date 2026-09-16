<?php

declare(strict_types=1);

namespace Indieinabox\Views\Admin;

/**
 * Presentation view component that renders the administrative interaction moderation interface.
 */
class ModerationView
{
    /**
     * Renders the moderation panel interface with pending comments and spam items.
     *
     * @param array<int, array<string, mixed>> $pending List of pending interaction records.
     * @param array<int, array<string, mixed>> $spam List of flagged spam interaction records.
     * @param string $fqdn Fully qualified domain name.
     * @return string Rendered HTML.
     */
    public static function render(array $pending, array $spam, string $fqdn): string
    {
        $fqdnClean = rtrim($fqdn, '/');
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
                            <strong><?= htmlspecialchars((string) ($item['author_name'] ?? 'Anonymous')) ?></strong> 
                            (<?= htmlspecialchars((string) ($item['interaction_type'] ?? 'interaction')) ?>) 
                            on <a href="<?= htmlspecialchars((string) ($item['target'] ?? '#')) ?>"><?= htmlspecialchars((string) ($item['target_hash'] ?? 'Target')) ?></a>
                        </div>
                        <?php if (!empty($item['url'])): ?>
                            <div style="font-size: 0.9em; margin-bottom: 0.5em;">
                                <a href="<?= htmlspecialchars((string) $item['url']) ?>" target="_blank"><?= htmlspecialchars((string) $item['url']) ?></a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($item['body']) || !empty($item['content'])): ?>
                            <div style="background: rgba(255,255,255,0.5); padding: 1em; border-left: 4px solid #ccc; margin: 1em 0; white-space: pre-wrap;"><?= htmlspecialchars((string) ($item['body'] ?: ($item['content'] ?? ''))) ?></div>
                        <?php endif; ?>
                        
                        <div style="display: flex; gap: 10px; margin-top: 1em;">
                            <form method="POST" action="<?= $fqdnClean ?>/admin/moderation">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="type" value="pending">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
                                <button type="submit" style="background: #28a745; color: white; border: none; padding: 8px 16px; cursor: pointer;">Approve</button>
                            </form>
                            <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" onsubmit="return confirm('Are you sure you want to delete this interaction?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="type" value="pending">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
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
                            <strong><?= htmlspecialchars((string) ($item['author_name'] ?? 'Anonymous')) ?></strong> 
                            (<?= htmlspecialchars((string) ($item['interaction_type'] ?? 'interaction')) ?>) 
                            on <a href="<?= htmlspecialchars((string) ($item['target'] ?? '#')) ?>"><?= htmlspecialchars((string) ($item['target_hash'] ?? 'Target')) ?></a>
                        </div>
                        <?php if (!empty($item['url'])): ?>
                            <div style="font-size: 0.9em; margin-bottom: 0.5em;">
                                <a href="<?= htmlspecialchars((string) $item['url']) ?>" target="_blank"><?= htmlspecialchars((string) $item['url']) ?></a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($item['body']) || !empty($item['content'])): ?>
                            <div style="background: rgba(255,255,255,0.5); padding: 1em; border-left: 4px solid #dc3545; margin: 1em 0; white-space: pre-wrap; opacity: 0.7;"><?= htmlspecialchars((string) ($item['body'] ?: ($item['content'] ?? ''))) ?></div>
                        <?php endif; ?>
                        
                        <div style="display: flex; gap: 10px; margin-top: 1em;">
                            <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" onsubmit="return confirm('Are you sure you want to approve this spam?');">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="type" value="spam">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
                                <button type="submit" style="background: #ffc107; color: #000; border: none; padding: 8px 16px; cursor: pointer;">Not Spam (Approve)</button>
                            </form>
                            <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" onsubmit="return confirm('Are you sure you want to delete this spam?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="type" value="spam">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
                                <button type="submit" style="background: #dc3545; color: white; border: none; padding: 8px 16px; cursor: pointer;">Delete Permanently</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        $inner = (string) ob_get_clean();
        return AdminLayoutView::render($inner, 'moderation', $fqdnClean);
    }
}
