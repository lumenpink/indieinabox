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
     * @param array<int, array<string, mixed>> $blocks List of federation defederation block rules.
     * @return string Rendered HTML.
     */
    public static function render(array $pending, array $spam, string $fqdn, array $blocks = []): string
    {
        $fqdnClean = rtrim($fqdn, '/');
        ob_start();
        ?>
        <style>
            .mod-container {
                max-width: 960px;
                margin: 0 auto;
                padding: 2.5rem 2rem;
                font-family: 'Outfit', sans-serif;
                color: var(--text-light, #f8fafc);
            }
            .mod-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 2rem;
                border-bottom: 1px solid var(--cyber-border, rgba(0, 240, 255, 0.25));
                padding-bottom: 1rem;
            }
            .mod-header h2 {
                margin: 0;
                font-size: 1.6rem;
                font-weight: 800;
                color: #fff;
                letter-spacing: 0.04em;
                text-shadow: 0 0 10px rgba(0, 240, 255, 0.3);
            }
            .mod-section-title {
                font-size: 1.1rem;
                font-weight: 700;
                letter-spacing: 0.06em;
                color: var(--neon-cyan, #00f0ff);
                margin: 2rem 0 1rem;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .mod-card {
                background: rgba(15, 20, 31, 0.85);
                border: 1px solid rgba(0, 240, 255, 0.2);
                border-left: 3px solid var(--neon-cyan, #00f0ff);
                border-radius: 6px;
                padding: 1.25rem;
                margin-bottom: 1.25rem;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
                transition: transform 0.2s, border-color 0.2s;
            }
            .mod-card:hover {
                border-color: rgba(0, 240, 255, 0.5);
                box-shadow: 0 6px 24px rgba(0, 240, 255, 0.12);
            }
            .mod-card.spam {
                border-color: rgba(255, 42, 133, 0.35);
                border-left: 3px solid var(--neon-pink, #ff2a85);
            }
            .mod-card.spam:hover {
                border-color: var(--neon-pink, #ff2a85);
                box-shadow: 0 6px 24px rgba(255, 42, 133, 0.15);
            }
            .mod-card-meta {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 8px;
                margin-bottom: 0.75rem;
                font-size: 0.95rem;
            }
            .mod-badge {
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.72rem;
                padding: 2px 8px;
                border-radius: 3px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .badge-cyan {
                background: rgba(0, 240, 255, 0.12);
                border: 1px solid rgba(0, 240, 255, 0.35);
                color: var(--neon-cyan, #00f0ff);
            }
            .badge-pink {
                background: rgba(255, 42, 133, 0.15);
                border: 1px solid rgba(255, 42, 133, 0.4);
                color: var(--neon-pink, #ff2a85);
            }
            .mod-body {
                background: rgba(8, 11, 16, 0.7);
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 4px;
                padding: 1rem;
                margin: 0.75rem 0;
                font-size: 0.92rem;
                line-height: 1.6;
                white-space: pre-wrap;
                color: #e2e8f0;
            }
            .mod-actions {
                display: flex;
                gap: 10px;
                margin-top: 1rem;
            }
            .mod-btn {
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.8rem;
                font-weight: 700;
                padding: 8px 16px;
                border-radius: 4px;
                cursor: pointer;
                border: none;
                transition: all 0.2s;
                text-transform: uppercase;
                letter-spacing: 0.06em;
            }
            .mod-btn-approve {
                background: var(--neon-green, #00ff9f);
                color: #06110a;
                box-shadow: 0 0 10px rgba(0, 255, 159, 0.25);
            }
            .mod-btn-approve:hover {
                box-shadow: 0 0 16px rgba(0, 255, 159, 0.5);
                transform: translateY(-1px);
            }
            .mod-btn-delete {
                background: transparent;
                border: 1px solid rgba(244, 63, 94, 0.6);
                color: #fda4af;
            }
            .mod-btn-delete:hover {
                background: rgba(244, 63, 94, 0.2);
                border-color: #f43f5e;
                color: #fff;
            }
            .mod-btn-unspam {
                background: var(--neon-amber, #ffd000);
                color: #1a1300;
                box-shadow: 0 0 10px rgba(255, 208, 0, 0.25);
            }
            .mod-empty {
                padding: 2rem;
                background: rgba(15, 20, 31, 0.5);
                border: 1px dashed rgba(0, 240, 255, 0.2);
                border-radius: 6px;
                color: var(--text-muted, #94a3b8);
                text-align: center;
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.9rem;
            }
        </style>
        <div class="mod-container">
            <div class="mod-header">
                <h2>// MODERATION QUEUE &mdash; Comment Moderation</h2>
                <span class="mod-badge badge-cyan">SYS.MOD</span>
            </div>
            
            <div class="mod-section-title">
                <span>[PENDING INTERACTIONS]</span>
            </div>
            <?php if (empty($pending)): ?>
                <div class="mod-empty">No pending comments or interactions to moderate.</div>
            <?php else: ?>
                <?php foreach ($pending as $item): ?>
                    <div class="mod-card">
                        <div class="mod-card-meta">
                            <strong><?= htmlspecialchars((string) ($item['author_name'] ?? 'Anonymous')) ?></strong> 
                            <span class="mod-badge badge-cyan"><?= htmlspecialchars((string) ($item['interaction_type'] ?? 'interaction')) ?></span>
                            <span>on <a href="<?= htmlspecialchars((string) ($item['target'] ?? '#')) ?>" style="color: var(--neon-cyan); text-decoration: none;"><?= htmlspecialchars((string) ($item['target_hash'] ?? 'Target')) ?></a></span>
                        </div>
                        <?php if (!empty($item['url'])): ?>
                            <div style="font-size: 0.85rem; margin-bottom: 0.5rem; font-family: 'JetBrains Mono', monospace;">
                                <a href="<?= htmlspecialchars((string) $item['url']) ?>" target="_blank" style="color: var(--text-muted);"><?= htmlspecialchars((string) $item['url']) ?></a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($item['body']) || !empty($item['content'])): ?>
                            <div class="mod-body"><?= htmlspecialchars((string) ($item['body'] ?: ($item['content'] ?? ''))) ?></div>
                        <?php endif; ?>
                        
                        <div class="mod-actions">
                            <form method="POST" action="<?= $fqdnClean ?>/admin/moderation">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="type" value="pending">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
                                <button type="submit" class="mod-btn mod-btn-approve">Approve</button>
                            </form>
                            <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" onsubmit="return confirm('Are you sure you want to delete this interaction?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="type" value="pending">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
                                <button type="submit" class="mod-btn mod-btn-delete">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="mod-section-title" style="color: var(--neon-pink);">
                <span>[FLAGGED SPAM QUEUE]</span>
            </div>
            <?php if (empty($spam)): ?>
                <div class="mod-empty">No spam detected in holding queue.</div>
            <?php else: ?>
                <?php foreach ($spam as $item): ?>
                    <div class="mod-card spam">
                        <div class="mod-card-meta">
                            <span class="mod-badge badge-pink">SPAM DETECTED</span>
                            <strong><?= htmlspecialchars((string) ($item['author_name'] ?? 'Anonymous')) ?></strong> 
                            <span class="mod-badge badge-cyan"><?= htmlspecialchars((string) ($item['interaction_type'] ?? 'interaction')) ?></span>
                            <span>on <a href="<?= htmlspecialchars((string) ($item['target'] ?? '#')) ?>" style="color: var(--neon-cyan); text-decoration: none;"><?= htmlspecialchars((string) ($item['target_hash'] ?? 'Target')) ?></a></span>
                        </div>
                        <?php if (!empty($item['url'])): ?>
                            <div style="font-size: 0.85rem; margin-bottom: 0.5rem; font-family: 'JetBrains Mono', monospace;">
                                <a href="<?= htmlspecialchars((string) $item['url']) ?>" target="_blank" style="color: var(--text-muted);"><?= htmlspecialchars((string) $item['url']) ?></a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($item['body']) || !empty($item['content'])): ?>
                            <div class="mod-body"><?= htmlspecialchars((string) ($item['body'] ?: ($item['content'] ?? ''))) ?></div>
                        <?php endif; ?>
                        
                        <div class="mod-actions">
                            <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" onsubmit="return confirm('Are you sure you want to approve this spam?');">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="type" value="spam">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
                                <button type="submit" class="mod-btn mod-btn-unspam">Not Spam (Approve)</button>
                            </form>
                            <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" onsubmit="return confirm('Are you sure you want to delete this spam?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="type" value="spam">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
                                <button type="submit" class="mod-btn mod-btn-delete">Delete Permanently</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="mod-section-title" style="color: #ff9800; margin-top: 3rem;">
                <span>[FEDERATION & DOMAIN BLOCKS]</span>
            </div>

            <div class="mod-card" style="border-left: 3px solid #ff9800; margin-bottom: 2rem;">
                <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                    <input type="hidden" name="action" value="add_block">
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 4px;">Target Domain</label>
                        <input type="text" name="domain" placeholder="bad-instance.social" style="width: 100%; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,240,255,0.3); color: #fff; padding: 6px 10px; border-radius: 4px; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 4px;">Or Actor URL</label>
                        <input type="url" name="actor_url" placeholder="https://instance.social/users/spammer" style="width: 100%; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,240,255,0.3); color: #fff; padding: 6px 10px; border-radius: 4px; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 4px;">Action</label>
                        <select name="block_action" style="width: 100%; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,240,255,0.3); color: #fff; padding: 6px 10px; border-radius: 4px; box-sizing: border-box;">
                            <option value="block">Block (Reject All)</option>
                            <option value="silence">Silence (Hide from feeds)</option>
                            <option value="reject_media">Reject Media</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 4px;">Reason / Audit Note</label>
                        <input type="text" name="reason" placeholder="Spam bot network" style="width: 100%; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,240,255,0.3); color: #fff; padding: 6px 10px; border-radius: 4px; box-sizing: border-box;">
                    </div>
                    <div>
                        <button type="submit" class="mod-btn" style="background: #ff9800; color: #000; font-weight: 700; width: 100%; height: 34px;">Add Block Rule</button>
                    </div>
                </form>
            </div>

            <?php if (empty($blocks)): ?>
                <div class="mod-empty">No domain or actor blocks active. Federation ingress is fully open.</div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 1px solid rgba(0,240,255,0.2); color: var(--text-muted);">
                                <th style="padding: 8px;">Target</th>
                                <th style="padding: 8px;">Action</th>
                                <th style="padding: 8px;">Reason</th>
                                <th style="padding: 8px;">Date</th>
                                <th style="padding: 8px; text-align: right;">Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blocks as $blk): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 8px; font-family: monospace;">
                                        <?= htmlspecialchars((string) ($blk['domain'] ?: $blk['actor_url'] ?: 'All')) ?>
                                    </td>
                                    <td style="padding: 8px;">
                                        <span class="mod-badge" style="background: <?= $blk['action'] === 'block' ? '#f44336' : ($blk['action'] === 'silence' ? '#ff9800' : '#2196f3') ?>; color: #fff;">
                                            <?= strtoupper(htmlspecialchars((string) $blk['action'])) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 8px; color: var(--text-muted);"><?= htmlspecialchars((string) ($blk['reason'] ?? '')) ?></td>
                                    <td style="padding: 8px; font-size: 0.8rem; color: var(--text-muted);"><?= date('Y-m-d H:i', (int) ($blk['created_at'] ?? time())) ?></td>
                                    <td style="padding: 8px; text-align: right;">
                                        <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" style="display: inline;" onsubmit="return confirm('Remove this moderation block?');">
                                            <input type="hidden" name="action" value="remove_block">
                                            <input type="hidden" name="id" value="<?= (int) $blk['id'] ?>">
                                            <button type="submit" class="mod-btn mod-btn-delete" style="padding: 2px 8px; font-size: 0.75rem;">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $inner = (string) ob_get_clean();
        return AdminLayoutView::render($inner, 'moderation', $fqdnClean);
    }
}
