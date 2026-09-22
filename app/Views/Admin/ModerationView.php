<?php

declare(strict_types=1);

namespace Indieinabox\Views\Admin;

/**
 * Presentation view component that renders the administrative interaction moderation interface.
 * Estética: Lambe-Lambe Periférico, Dissidência Travesti & Resistência DIY.
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
                max-width: 1050px;
                margin: 0 auto;
                padding: 2rem;
                font-family: 'Outfit', sans-serif;
                color: var(--text-light, #f4efe6);
            }
            .mod-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 2rem;
                border-bottom: 3px solid #000000;
                padding-bottom: 1.25rem;
            }
            .mod-header h2 {
                margin: 0;
                font-family: 'Bebas Neue', 'Archivo Black', sans-serif;
                font-size: 2.2rem;
                font-weight: 800;
                color: #f4efe6;
                letter-spacing: 0.04em;
                text-transform: uppercase;
            }
            .mod-section-title {
                font-family: 'Archivo Black', sans-serif;
                font-size: 1rem;
                font-weight: 800;
                letter-spacing: 0.06em;
                color: var(--tint-yellow, #ffe600);
                margin: 2.5rem 0 1rem;
                display: flex;
                align-items: center;
                gap: 8px;
                text-transform: uppercase;
            }
            .mod-card {
                background: #1e1f25;
                border: 2px solid #000000;
                border-left: 6px solid var(--tint-yellow, #ffe600);
                box-shadow: 4px 4px 0 #000000;
                padding: 1.5rem;
                margin-bottom: 1.5rem;
                transition: transform 0.15s, box-shadow 0.15s;
                position: relative;
            }
            .mod-card:hover {
                transform: translate(-1px, -1px);
                box-shadow: 6px 6px 0 #000000;
            }
            .mod-card.spam {
                border-left: 6px solid var(--tint-magenta, #ff007f);
            }
            .mod-card-meta {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 10px;
                margin-bottom: 0.75rem;
                font-size: 0.95rem;
            }
            .mod-body {
                background: #0a0a0c;
                border: 2px solid #000000;
                padding: 1rem 1.25rem;
                margin: 0.75rem 0 1rem;
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.9rem;
                line-height: 1.6;
                white-space: pre-wrap;
                color: #f4efe6;
                box-shadow: inset 2px 2px 0 rgba(0,0,0,0.6);
            }
            .mod-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-top: 1rem;
            }
            .mod-empty {
                padding: 2.5rem;
                background: #141518;
                border: 2px dashed #444444;
                color: var(--text-muted, #a0a4b0);
                text-align: center;
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.9rem;
            }
        </style>
        <div class="mod-container">
            <div class="mod-header">
                <div>
                    <h2>MESA DE MODERAÇÃO &mdash; Comment Moderation</h2>
                    <span class="badge-stamp badge-cyan">MODERAÇÃO WEBMENTION & ATIVIDADE FEDERADA</span>
                </div>
                <span class="badge-stamp badge-yellow">AUTO-DEFESA</span>
            </div>
            
            <div class="mod-section-title">
                <span>[INTERAÇÕES PENDENTES DE APROVAÇÃO]</span>
            </div>
            <?php if (empty($pending)): ?>
                <div class="mod-empty">Nenhum comentário ou interação pendente no momento.</div>
            <?php else: ?>
                <?php foreach ($pending as $item): ?>
                    <div class="mod-card">
                        <div class="mod-card-meta">
                            <strong style="font-size: 1.05rem;"><?= htmlspecialchars((string) ($item['author_name'] ?? 'Anônimo')) ?></strong> 
                            <span class="badge-stamp badge-yellow"><?= htmlspecialchars((string) ($item['interaction_type'] ?? 'interaction')) ?></span>
                            <span style="font-family: 'JetBrains Mono'; font-size: 0.85rem;">em <a href="<?= htmlspecialchars((string) ($item['target'] ?? '#')) ?>" style="color: var(--tint-cyan); text-decoration: underline;"><?= htmlspecialchars((string) ($item['target_hash'] ?? 'Target')) ?></a></span>
                        </div>
                        <?php if (!empty($item['url'])): ?>
                            <div style="font-size: 0.82rem; margin-bottom: 0.5rem; font-family: 'JetBrains Mono', monospace;">
                                <a href="<?= htmlspecialchars((string) $item['url']) ?>" target="_blank" style="color: var(--text-muted);"><?= htmlspecialchars((string) $item['url']) ?></a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($item['body']) || !empty($item['content'])): ?>
                            <div class="mod-body"><?= htmlspecialchars((string) ($item['body'] ?: ($item['content'] ?? ''))) ?></div>
                        <?php endif; ?>
                        
                        <div class="mod-actions">
                            <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" style="margin: 0;">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="type" value="pending">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
                                <button type="submit" class="btn-stamp btn-stamp-yellow">✓ APROVAR & PUBLICAR</button>
                            </form>
                            <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" onsubmit="return confirm('Deseja realmente excluir esta interação?');" style="margin: 0;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="type" value="pending">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
                                <button type="submit" class="btn-stamp btn-stamp-red">✕ EXCLUIR</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="mod-section-title" style="color: var(--tint-magenta);">
                <span>[FILA DE QUARENTENA // SPAM IDENTIFICADO]</span>
            </div>
            <?php if (empty($spam)): ?>
                <div class="mod-empty">Nenhum registro de spam em quarentena.</div>
            <?php else: ?>
                <?php foreach ($spam as $item): ?>
                    <div class="mod-card spam">
                        <div class="mod-card-meta">
                            <span class="badge-stamp badge-magenta">SPAM BLOQUEADO</span>
                            <strong style="font-size: 1.05rem;"><?= htmlspecialchars((string) ($item['author_name'] ?? 'Anônimo')) ?></strong> 
                            <span class="badge-stamp badge-cyan"><?= htmlspecialchars((string) ($item['interaction_type'] ?? 'interaction')) ?></span>
                            <span style="font-family: 'JetBrains Mono'; font-size: 0.85rem;">em <a href="<?= htmlspecialchars((string) ($item['target'] ?? '#')) ?>" style="color: var(--tint-cyan); text-decoration: underline;"><?= htmlspecialchars((string) ($item['target_hash'] ?? 'Target')) ?></a></span>
                        </div>
                        <?php if (!empty($item['url'])): ?>
                            <div style="font-size: 0.82rem; margin-bottom: 0.5rem; font-family: 'JetBrains Mono', monospace;">
                                <a href="<?= htmlspecialchars((string) $item['url']) ?>" target="_blank" style="color: var(--text-muted);"><?= htmlspecialchars((string) $item['url']) ?></a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($item['body']) || !empty($item['content'])): ?>
                            <div class="mod-body"><?= htmlspecialchars((string) ($item['body'] ?: ($item['content'] ?? ''))) ?></div>
                        <?php endif; ?>
                        
                        <div class="mod-actions">
                            <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" onsubmit="return confirm('Deseja realmente marcar como legítimo e aprovar?');" style="margin: 0;">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="type" value="spam">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
                                <button type="submit" class="btn-stamp btn-stamp-cyan">✓ NÃO É SPAM (APROVAR)</button>
                            </form>
                            <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" onsubmit="return confirm('Deseja apagar definitivamente este spam?');" style="margin: 0;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="type" value="spam">
                                <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
                                <button type="submit" class="btn-stamp btn-stamp-red">✕ EXCLUIR DEFINITIVAMENTE</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="mod-section-title" style="color: var(--tint-yellow); margin-top: 3.5rem;">
                <span>[DEFEDERAÇÃO & BLOQUEIOS DE DOMÍNIO / ATOR]</span>
            </div>

            <div class="mod-card" style="border-left: 6px solid var(--tint-yellow); margin-bottom: 2rem;">
                <span class="tape-label">DEFESA DE MALHA</span>
                <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                    <input type="hidden" name="action" value="add_block">
                    <div>
                        <label style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); display: block; margin-bottom: 4px;">DOMÍNIO ALVO</label>
                        <input type="text" name="domain" placeholder="instancia-troll.xyz" style="width: 100%;">
                    </div>
                    <div>
                        <label style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); display: block; margin-bottom: 4px;">OU URL DO ATOR ESPECÍFICO</label>
                        <input type="url" name="actor_url" placeholder="https://instancia.xyz/users/spammer" style="width: 100%;">
                    </div>
                    <div>
                        <label style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); display: block; margin-bottom: 4px;">AÇÃO DE INTERDIÇÃO</label>
                        <select name="block_action" style="width: 100%;">
                            <option value="block">Bloquear (Rejeitar tudo)</option>
                            <option value="silence">Silenciar (Ocultar da timeline pública)</option>
                            <option value="reject_media">Rejeitar anexos de mídia</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); display: block; margin-bottom: 4px;">MOTIVO / AUDITORIA</label>
                        <input type="text" name="reason" placeholder="Rede de bots de spam" style="width: 100%;">
                    </div>
                    <div>
                        <button type="submit" class="btn-stamp btn-stamp-magenta" style="width: 100%; height: 42px;">+ APLICAR BLOQUEIO</button>
                    </div>
                </form>
            </div>

            <?php if (empty($blocks)): ?>
                <div class="mod-empty">Nenhum domínio ou ator bloqueado. A malha federada está operando aberta.</div>
            <?php else: ?>
                <div class="cyber-table-wrap">
                    <table class="cyber-table">
                        <thead>
                            <tr>
                                <th>ALVO DE BLOQUEIO</th>
                                <th>AÇÃO APLICADA</th>
                                <th>MOTIVO AUDITADO</th>
                                <th>DATA</th>
                                <th style="text-align: right;">AÇÕES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blocks as $blk): ?>
                                <tr>
                                    <td style="font-family: 'JetBrains Mono', monospace; font-weight: 700;">
                                        <?= htmlspecialchars((string) ($blk['domain'] ?: $blk['actor_url'] ?: 'Todos')) ?>
                                    </td>
                                    <td>
                                        <span class="badge-stamp <?= $blk['action'] === 'block' ? 'badge-red' : ($blk['action'] === 'silence' ? 'badge-yellow' : 'badge-cyan') ?>">
                                            <?= strtoupper(htmlspecialchars((string) $blk['action'])) ?>
                                        </span>
                                    </td>
                                    <td style="color: var(--text-muted); font-size: 0.85rem;"><?= htmlspecialchars((string) ($blk['reason'] ?? '')) ?></td>
                                    <td style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: var(--text-muted);"><?= date('Y-m-d H:i', (int) ($blk['created_at'] ?? time())) ?></td>
                                    <td style="text-align: right;">
                                        <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" style="display: inline; margin: 0;" onsubmit="return confirm('Deseja realmente remover este bloqueio?');">
                                            <input type="hidden" name="action" value="remove_block">
                                            <input type="hidden" name="id" value="<?= (int) $blk['id'] ?>">
                                            <button type="submit" class="btn-stamp btn-stamp-red" style="padding: 4px 10px; font-size: 0.72rem;">DESBLOQUEAR</button>
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
