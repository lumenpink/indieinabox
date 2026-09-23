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
            .mod-dashboard-wrap {
                max-width: 1180px;
                margin: 0 auto;
                padding: 2.5rem 1.75rem;
                font-family: 'Outfit', sans-serif;
            }

            /* Linha Superior: Cartaz Principal + Notas Adesivas */
            .mod-top-row {
                display: grid;
                grid-template-columns: 1.8fr 1fr 1fr;
                gap: 1.5rem;
                margin-bottom: 2rem;
                align-items: stretch;
            }

            @media (max-width: 960px) {
                .mod-top-row {
                    grid-template-columns: 1fr;
                }
            }

            /* Cartaz Principal do Dashboard */
            .mod-hero-poster {
                background: var(--lambe-paper);
                color: var(--lambe-ink-black);
                border: 2px solid #000000;
                box-shadow: 4px 4px 0px #000000;
                padding: 1.75rem 1.75rem 1rem;
                position: relative;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }

            .mod-hero-poster .sub-label {
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.78rem;
                font-weight: 800;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                color: #555;
                margin-bottom: 0.5rem;
            }

            .mod-hero-poster h1 {
                margin: 0 0 1rem;
                font-family: 'Bebas Neue', 'Archivo Black', sans-serif;
                font-size: 2.75rem;
                line-height: 0.95;
                letter-spacing: 0.03em;
                text-transform: uppercase;
                color: #000000;
            }

            /* Notas Adesivas com Fita no Topo */
            .mod-stickynote {
                background: var(--lambe-paper);
                color: #000000;
                border: 2px solid #000000;
                box-shadow: 4px 4px 0px #000000;
                padding: 1.5rem 1.25rem 1rem;
                position: relative;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                transform: rotate(-0.8deg);
                transition: transform 0.15s;
            }

            .mod-stickynote:nth-child(even) {
                transform: rotate(0.8deg);
            }

            .mod-stickynote:hover {
                transform: rotate(0deg) translate(-1px, -1px);
                box-shadow: 5px 5px 0 #000;
            }

            .mod-stickynote-tape {
                position: absolute;
                top: -11px;
                left: 50%;
                width: 70px;
                height: 22px;
                margin-left: -35px;
                background: var(--tape-cream);
                border: 1px solid rgba(0,0,0,0.18);
                box-shadow: 1px 1px 2px rgba(0,0,0,0.15);
                transform: rotate(-1.5deg);
                z-index: 5;
            }

            .mod-stickynote .note-stat-group {
                display: flex;
                justify-content: space-around;
                gap: 10px;
                margin-top: auto;
            }

            .mod-stickynote .note-stat-item {
                flex: 1;
            }

            .mod-stickynote .note-label {
                font-family: 'Bebas Neue', 'Archivo Black', sans-serif;
                font-size: 1.15rem;
                line-height: 1.1;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                color: #000000;
                margin-bottom: 0.25rem;
            }

            .mod-stickynote .note-value {
                font-family: 'Bebas Neue', sans-serif;
                font-size: 3.5rem;
                font-weight: 900;
                line-height: 1;
                color: #000000;
            }

            /* Cartaz da Mesa de Atividades / Tabela */
            .mod-activities-poster {
                background: var(--lambe-paper);
                color: var(--lambe-ink-black);
                border: 2px solid #000000;
                box-shadow: 4px 4px 0px #000000;
                margin-bottom: 2.5rem;
                position: relative;
            }

            .mod-activities-banner {
                display: flex;
                align-items: stretch;
                background: var(--tint-yellow);
                border-bottom: 2px solid #000000;
            }

            .mod-activities-banner .banner-title {
                font-family: 'Bebas Neue', 'Archivo Black', sans-serif;
                font-size: 1.5rem;
                padding: 8px 18px;
                color: #000000;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                display: flex;
                align-items: center;
                white-space: nowrap;
            }

            .mod-activities-banner .banner-stripes {
                flex: 1;
                background: repeating-linear-gradient(-45deg, #ffe600, #ffe600 10px, #000000 10px, #000000 20px);
                border-left: 2px solid #000000;
                min-height: 38px;
            }

            /* Form / Interações */
            .mod-table-container {
                padding: 1.5rem;
                overflow-x: auto;
            }

            .mod-item-row {
                background: #ffffff;
                border: 2px solid #000000;
                padding: 1.25rem 1.5rem;
                margin-bottom: 1.25rem;
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                box-shadow: 2px 2px 0 #000;
            }

            .mod-item-meta {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                border-bottom: 1px dashed #000;
                padding-bottom: 0.5rem;
            }

            .mod-body-paper {
                background: var(--lambe-paper-dark);
                border: 1px solid #000000;
                padding: 0.9rem 1.2rem;
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.9rem;
                color: #000000;
                line-height: 1.5;
            }

            /* Carimbos como botões de formulário */
            .stamp-submit-btn {
                background: none;
                border: none;
                padding: 0;
                cursor: pointer;
            }

            .stamp-submit-btn .stamp-badge {
                transition: transform 0.1s, box-shadow 0.1s;
            }

            .stamp-submit-btn:hover .stamp-badge {
                transform: translate(-1px, -1px);
                box-shadow: 3px 3px 0 #000;
            }

            .stamp-submit-btn:active .stamp-badge {
                transform: translate(1px, 1px);
                box-shadow: 1px 1px 0 #000;
            }
        </style>

        <div class="mod-dashboard-wrap">
            <!-- 01: Top Row: Main Header Poster + Sticky Stat Notes -->
            <div class="mod-top-row">
                <div class="mod-hero-poster">
                    <span class="tape-corner-tl"></span>
                    <span class="tape-corner-tr"></span>
                    <div>
                        <div class="sub-label">MAIN DASHBOARD // AUTONOMOUS SECURITY</div>
                        <h1>MODERAÇÃO &amp;<br>DEFERAÇÃO POPULAR</h1>
                    </div>
                    <div class="hazard-stripes" style="margin-bottom: 0;"></div>
                </div>

                <div class="mod-stickynote">
                    <div class="mod-stickynote-tape"></div>
                    <div class="mod-stickynote .note-stat-group" style="display: flex; gap: 12px;">
                        <div class="note-stat-item">
                            <div class="note-label">BLOQUEIOS ATIVOS:</div>
                            <div class="note-value"><?= count($blocks) ?></div>
                        </div>
                        <div class="note-stat-item">
                            <div class="note-label">INSTÂNCIAS SILENCIADAS:</div>
                            <div class="note-value"><?= count(array_filter($blocks, fn($b) => ($b['action'] ?? '') === 'silence')) ?></div>
                        </div>
                    </div>
                    <div class="hazard-stripes" style="margin: 0.75rem 0 0; height: 10px;"></div>
                </div>

                <div class="mod-stickynote">
                    <div class="mod-stickynote-tape"></div>
                    <div class="mod-stickynote .note-stat-group" style="display: flex; gap: 12px;">
                        <div class="note-stat-item">
                            <div class="note-label">INTERAÇÕES PENDENTES:</div>
                            <div class="note-value" style="color: var(--tint-magenta);"><?= count($pending) ?></div>
                        </div>
                        <div class="note-stat-item">
                            <div class="note-label">QUARENTENA / SPAM:</div>
                            <div class="note-value"><?= count($spam) ?></div>
                        </div>
                    </div>
                    <div class="hazard-stripes" style="margin: 0.75rem 0 0; height: 10px;"></div>
                </div>
            </div>

            <!-- 02: Middle: Atividades de Moderação (Interações Pendentes) -->
            <div class="mod-activities-poster">
                <span class="tape-corner-tl"></span>
                <span class="tape-corner-tr"></span>
                
                <div class="mod-activities-banner">
                    <span class="banner-title">MODERAÇÃO ACTIVIDADES</span>
                    <div class="banner-stripes"></div>
                </div>

                <div class="mod-table-container">
                    <?php if (empty($pending)): ?>
                        <div style="padding: 2.5rem; text-align: center; font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #555; border: 2px dashed #000; background: #faf7f2;">
                            [NENHUMA INTERAÇÃO PENDENTE DE APROVAÇÃO // MALHA PACIFICADA]
                        </div>
                    <?php else: ?>
                        <div class="cyber-table-wrap" style="margin-top: 0;">
                            <table class="cyber-table">
                                <thead>
                                    <tr>
                                        <th style="width: 140px;">DATA</th>
                                        <th style="width: 180px;">AUTOR / ORIGEM</th>
                                        <th>CONTEÚDO &amp; ALVO</th>
                                        <th style="width: 220px; text-align: right;">AÇÕES</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending as $item): ?>
                                        <tr>
                                            <td style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; font-weight: 700;">
                                                <?= date('d/m/Y H:i', is_numeric($item['created_at'] ?? null) ? (int)$item['created_at'] : time()) ?>
                                            </td>
                                            <td>
                                                <strong style="font-size: 0.95rem;"><?= htmlspecialchars((string) ($item['author_name'] ?? 'Anônimo')) ?></strong>
                                                <?php if (!empty($item['url'])): ?>
                                                    <div style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: #666; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                        <a href="<?= htmlspecialchars((string) $item['url']) ?>" target="_blank" style="color: inherit; text-decoration: underline;"><?= htmlspecialchars((string) $item['url']) ?></a>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge-stamp badge-yellow" style="margin-bottom: 4px;"><?= htmlspecialchars(strtoupper((string) ($item['interaction_type'] ?? 'interaction'))) ?></span>
                                                <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.78rem; color: #444;">
                                                    em <a href="<?= htmlspecialchars((string) ($item['target'] ?? '#')) ?>" target="_blank" style="color: #000; text-decoration: underline; font-weight: 700;"><?= htmlspecialchars((string) ($item['target_hash'] ?? 'Target')) ?></a>
                                                </span>
                                                <?php $bodyText = (string) ($item['body'] ?? $item['content'] ?? ''); ?>
                                                <?php if ($bodyText !== ''): ?>
                                                    <div class="mod-body-paper" style="margin-top: 6px;">
                                                        <?= htmlspecialchars($bodyText) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right; vertical-align: middle;">
                                                <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                                                    <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" style="margin: 0;">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="type" value="pending">
                                                        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
                                                        <button type="submit" class="stamp-submit-btn" title="Aprovar e publicar na timeline">
                                                            <span class="stamp-badge stamp-approve">[APROVAR]</span>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" onsubmit="return confirm('Deseja realmente suspender/excluir esta interação?');" style="margin: 0;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="type" value="pending">
                                                        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
                                                        <button type="submit" class="stamp-submit-btn" title="Suspender e descartar">
                                                            <span class="stamp-badge stamp-suspend">[SUSPEND]</span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="hazard-stripes" style="margin: 0;"></div>
            </div>

            <!-- 03: Fila de Quarentena / Spam Identificado -->
            <?php if (!empty($spam)): ?>
                <div class="mod-activities-poster" style="border-left: 6px solid var(--tint-magenta);">
                    <div class="mod-activities-banner" style="background: var(--tint-magenta);">
                        <span class="banner-title" style="color: #ffffff;">FILA DE QUARENTENA // SPAM IDENTIFICADO</span>
                        <div class="banner-stripes" style="background: repeating-linear-gradient(-45deg, var(--tint-magenta), var(--tint-magenta) 10px, #000000 10px, #000000 20px);"></div>
                    </div>
                    <div class="mod-table-container">
                        <div class="cyber-table-wrap" style="margin-top: 0;">
                            <table class="cyber-table">
                                <thead>
                                    <tr>
                                        <th style="width: 140px;">DATA</th>
                                        <th style="width: 180px;">AUTOR / ORIGEM</th>
                                        <th>CONTEÚDO DO SPAM</th>
                                        <th style="width: 240px; text-align: right;">AÇÕES DE CARIMBO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($spam as $item): ?>
                                        <tr>
                                            <td style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; font-weight: 700;">
                                                <?= date('d/m/Y H:i', is_numeric($item['created_at'] ?? null) ? (int)$item['created_at'] : time()) ?>
                                            </td>
                                            <td>
                                                <strong style="font-size: 0.95rem;"><?= htmlspecialchars((string) ($item['author_name'] ?? 'Anônimo')) ?></strong>
                                                <span class="badge-stamp badge-magenta">SPAM</span>
                                            </td>
                                            <td>
                                                <div class="mod-body-paper">
                                                    <?= htmlspecialchars((string) ($item['body'] ?: ($item['content'] ?? ''))) ?>
                                                </div>
                                            </td>
                                            <td style="text-align: right; vertical-align: middle;">
                                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                                    <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" style="margin: 0;">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="type" value="spam">
                                                        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
                                                        <button type="submit" class="stamp-submit-btn">
                                                            <span class="stamp-badge stamp-silence">[NÃO É SPAM]</span>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" onsubmit="return confirm('Deseja apagar definitivamente?');" style="margin: 0;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="type" value="spam">
                                                        <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($item['id_filename'] ?? '')) ?>">
                                                        <button type="submit" class="stamp-submit-btn">
                                                            <span class="stamp-badge stamp-suspend">[ELIMINAR]</span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 04: Cartaz de Defederação & Bloqueios -->
            <div class="card-lambe has-tape">
                <span class="tape-corner-bl"></span>
                <span class="tape-corner-br"></span>

                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 0.75rem; margin-bottom: 1.5rem;">
                    <div>
                        <h2 style="margin: 0; font-family: 'Bebas Neue', 'Archivo Black', sans-serif; font-size: 2rem; letter-spacing: 0.04em; text-transform: uppercase;">
                            DEFEDERAÇÃO &amp; DEFESA DE MALHA
                        </h2>
                        <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.78rem; font-weight: 800; color: #444;">
                            CONTROLE DE INSTÂNCIAS TROLLS, SPAMMERS E MALICIOSAS
                        </span>
                    </div>
                    <span class="badge-stamp badge-yellow">AUTO-DEFESA</span>
                </div>

                <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end; background: #ffffff; border: 2px solid #000; padding: 1.25rem; box-shadow: 2px 2px 0 #000; margin-bottom: 1.5rem;">
                    <input type="hidden" name="action" value="add_block">
                    <div>
                        <label>DOMÍNIO ALVO</label>
                        <input type="text" name="domain" placeholder="instancia-troll.xyz" style="width: 100%;">
                    </div>
                    <div>
                        <label>OU URL DO ATOR ESPECÍFICO</label>
                        <input type="url" name="actor_url" placeholder="https://instancia.xyz/users/spammer" style="width: 100%;">
                    </div>
                    <div>
                        <label>AÇÃO DE INTERDIÇÃO</label>
                        <select name="block_action" style="width: 100%;">
                            <option value="block">Bloquear (Rejeitar tudo)</option>
                            <option value="silence">Silenciar (Ocultar da timeline pública)</option>
                            <option value="reject_media">Rejeitar anexos de mídia</option>
                        </select>
                    </div>
                    <div>
                        <label>MOTIVO / AUDITORIA</label>
                        <input type="text" name="reason" placeholder="Rede de bots de spam" style="width: 100%;">
                    </div>
                    <div>
                        <button type="submit" class="btn-stamp btn-stamp-magenta" style="width: 100%; height: 44px;">+ APLICAR BLOQUEIO</button>
                    </div>
                </form>

                <?php if (empty($blocks)): ?>
                    <div style="padding: 1.5rem; text-align: center; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; font-weight: 700; color: #666; border: 1px dashed #000;">
                        Nenhum domínio ou ator bloqueado. A malha federada está operando aberta.
                    </div>
                <?php else: ?>
                    <div class="cyber-table-wrap" style="margin: 0;">
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
                                        <td style="font-family: 'JetBrains Mono', monospace; font-weight: 800; font-size: 0.92rem;">
                                            <?= htmlspecialchars((string) ($blk['domain'] ?: $blk['actor_url'] ?: 'Todos')) ?>
                                        </td>
                                        <td>
                                            <?php
                                                $action = strtolower((string) ($blk['action'] ?? 'block'));
                                                $stampClass = match ($action) {
                                                    'block', 'suspend' => 'stamp-suspend',
                                                    'silence' => 'stamp-silence',
                                                    default => 'stamp-filter',
                                                };
                                            ?>
                                            <span class="stamp-badge <?= $stampClass ?>">
                                                [<?= strtoupper(htmlspecialchars((string) $blk['action'])) ?>]
                                            </span>
                                        </td>
                                        <td style="font-size: 0.88rem;"><?= htmlspecialchars((string) ($blk['reason'] ?? '')) ?></td>
                                        <td style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: #555;"><?= date('Y-m-d H:i', (int) ($blk['created_at'] ?? time())) ?></td>
                                        <td style="text-align: right;">
                                            <form method="POST" action="<?= $fqdnClean ?>/admin/moderation" style="display: inline; margin: 0;" onsubmit="return confirm('Deseja realmente remover este bloqueio?');">
                                                <input type="hidden" name="action" value="remove_block">
                                                <input type="hidden" name="id" value="<?= (int) ($blk['id'] ?? 0) ?>">
                                                <button type="submit" class="btn-stamp btn-stamp-red" style="padding: 4px 12px; font-size: 0.72rem;">DESBLOQUEAR</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $inner = (string) ob_get_clean();
        return AdminLayoutView::render($inner, 'moderation', $fqdnClean);
    }
}
