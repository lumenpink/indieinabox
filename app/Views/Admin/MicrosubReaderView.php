<?php

declare(strict_types=1);

namespace Indieinabox\Views\Admin;

/**
 * Presentation view component that renders the Microsub web reader interface.
 * Estética: Lambe-Lambe Periférico, Dissidência Travesti & Resistência DIY.
 */
class MicrosubReaderView
{
    /**
     * Renders the Microsub web reader interface wrapped in the admin layout.
     *
     * @param string $fqdn Fully qualified domain name of the site.
     * @return string Rendered HTML.
     */
    public static function render(string $fqdn): string
    {
        $fqdnClean = rtrim($fqdn, '/');
        $endpoint = $fqdnClean . '/microsub';
        ob_start();
        ?>
    <style>
        .reader-container {
            max-width: 1050px;
            margin: 0 auto;
            padding: 2rem;
            font-family: 'Outfit', sans-serif;
            color: var(--lambe-ink-black, #000000);
        }

        .timeline-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-bottom: 1.25rem;
            margin-bottom: 2rem;
            border-bottom: 3px solid #000000;
            position: relative;
        }

        .timeline-header h1 {
            margin: 0;
            font-family: 'Bebas Neue', 'Archivo Black', sans-serif;
            font-size: 2.2rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--lambe-ink-black, #000000);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .timeline-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .timeline-search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 260px;
        }

        .timeline-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        #timeline {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* Card de Post Estilo Cartaz Colado */
        .item-post {
            background: var(--lambe-paper, #f4efe6);
            border: 2px solid #000000;
            border-left: 6px solid var(--tint-yellow, #ffe600);
            box-shadow: 4px 4px 0px #000000;
            padding: 1.5rem;
            position: relative;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .item-post:hover {
            transform: translate(-1px, -1px);
            box-shadow: 6px 6px 0px #000000;
        }

        .item-post.read {
            border-left-color: #aaaaaa;
            opacity: 0.75;
        }

        .item-post-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            border-bottom: 1px dashed #bbbbbb;
            padding-bottom: 0.75rem;
        }

        .item-author-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .item-author-avatar {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border: 2px solid #000000;
            box-shadow: 2px 2px 0 #000000;
            background: #000;
        }

        .item-author-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.05rem;
            color: #000000;
        }

        .item-date {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.78rem;
            color: #666666;
        }

        .item-content {
            font-size: 1.02rem;
            line-height: 1.7;
            color: #000000;
            margin-bottom: 1.25rem;
            word-break: break-word;
        }

        .item-content a {
            color: var(--tint-magenta, #ff007f);
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .item-content a:hover {
            color: #000000;
            background: var(--tint-yellow, #ffe600);
        }

        .item-content img {
            max-width: 100%;
            border: 2px solid #000000;
            box-shadow: 3px 3px 0 #000000;
            margin-top: 1rem;
            display: block;
        }

        .item-actions-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            padding-top: 1rem;
            border-top: 2px solid #000000;
        }

        /* Fallback Content Warning */
        .cw-fallback details {
            background: rgba(255, 34, 68, 0.08);
            border: 2px solid var(--tint-red);
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
        .cw-fallback summary {
            cursor: pointer;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 800;
            color: var(--tint-red);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .cw-fallback img {
            filter: blur(25px);
            cursor: pointer;
            transition: filter 0.3s ease;
        }
        .cw-fallback img.revealed {
            filter: none;
        }

        /* Modais Brutalistas */
        .brutalist-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .brutalist-modal {
            background: var(--lambe-paper, #f4efe6);
            border: 3px solid #000000;
            box-shadow: 8px 8px 0 #000000;
            width: 100%;
            max-width: 680px;
            max-height: 88vh;
            overflow-y: auto;
            padding: 2rem;
            position: relative;
        }

        .brutalist-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000000;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .brutalist-modal-header h2 {
            margin: 0;
            font-family: 'Bebas Neue', 'Archivo Black', sans-serif;
            font-size: 1.8rem;
            letter-spacing: 0.05em;
            color: #000000;
            text-transform: uppercase;
        }
    </style>

    <div class="reader-container">
        <div id="reader-view">
            <header class="timeline-header">
                <div>
                    <h1 id="current-channel-title">INBOX</h1>
                    <span class="badge-stamp badge-yellow">TIMELINE & FEDERATION READER</span>
                </div>

                <div class="timeline-actions">
                    <button class="btn-stamp" id="push-toggle-btn" onclick="togglePushNotifications()">🔔 ALERTAS PUSH</button>
                    <button class="btn-stamp btn-stamp-cyan" onclick="addFeed()">+ ASSINAR FEED</button>
                    <button class="btn-stamp" onclick="manageFeeds()">FEEDS</button>
                    <button class="btn-stamp" onclick="manageFilters()">REGRAS & FILTROS</button>
                    <button class="btn-stamp btn-stamp-yellow" onclick="fetchFeeds()">⚡ SINCRONIZAR</button>
                </div>
            </header>

            <div class="timeline-controls" style="margin-bottom: 1.5rem; background: var(--lambe-paper, #f4efe6); border: 2px solid #000; padding: 1rem; box-shadow: 3px 3px 0 #000;">
                <div class="timeline-search-box">
                    <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; font-weight: 800; color: #000000;">BUSCAR:</span>
                    <input type="text" id="article-search" placeholder="Filtrar por texto, autor ou termo..." style="flex: 1;" oninput="filterTimeline()">
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; font-weight: 800; color: #000000;">ESTADO:</span>
                    <select id="article-filter" onchange="filterTimeline()">
                        <option value="all">TODOS OS POSTS</option>
                        <option value="unread">APENAS NÃO LIDOS</option>
                        <option value="read">APENAS JÁ LIDOS</option>
                    </select>
                </div>
            </div>

            <main id="timeline">
                <p style="text-align: center; color: var(--text-muted); font-family: 'JetBrains Mono', monospace; padding: 3rem;">CARREGANDO TIMELINE DO MURO...</p>
            </main>
        </div>
    </div>

    <!-- Modal Gerenciador de Feeds -->
    <div id="manage-feeds-modal" class="brutalist-modal-overlay">
        <div class="brutalist-modal">
            <span class="tape-label">ASSINATURAS DO CANAL</span>
            <div class="brutalist-modal-header">
                <h2>GERENCIAR FEEDS</h2>
                <button class="btn-stamp btn-stamp-red" onclick="closeManageFeeds()">FECHAR [X]</button>
            </div>
            <div id="manage-feeds-list">Carregando lista de fontes...</div>
        </div>
    </div>

    <!-- Modal Filtros & Moderação -->
    <div id="manage-filters-modal" class="brutalist-modal-overlay">
        <div class="brutalist-modal">
            <span class="tape-label">REGRAS DE RETENÇÃO</span>
            <div class="brutalist-modal-header">
                <h2>FILTROS & MODERAÇÃO DA TIMELINE</h2>
                <button class="btn-stamp btn-stamp-red" onclick="closeManageFilters()">FECHAR [X]</button>
            </div>
            
            <div style="background: var(--lambe-paper, #f4efe6); border: 2px solid #000; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 3px 3px 0 #000;">
                <h3 style="margin-top: 0; font-family: 'Archivo Black', sans-serif; font-size: 0.95rem; color: #000000; text-transform: uppercase;">
                    + ADICIONAR NOVA REGRA
                </h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div>
                        <label style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; font-weight: 800; color: #000000; display: block; margin-bottom: 4px;">TIPO DE REGRA</label>
                        <select id="new-filter-type" style="width: 100%;">
                            <option value="keyword">Palavra-chave (conteúdo/título)</option>
                            <option value="author">Autor (nome/URL)</option>
                            <option value="domain">Domínio / Instância</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; font-weight: 800; color: #000000; display: block; margin-bottom: 4px;">AÇÃO</label>
                        <select id="new-filter-action" style="width: 100%;">
                            <option value="mute">Silenciar (Ocultar da timeline)</option>
                            <option value="auto_archive">Auto-Arquivar (Marcar como lido)</option>
                            <option value="drop">Descartar no sync</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; font-weight: 800; color: #000000; display: block; margin-bottom: 4px;">PADRÃO / TERMO / DOMÍNIO</label>
                        <input type="text" id="new-filter-content" placeholder="ex: spammer.xyz, bot, crypto" style="width: 100%; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; font-weight: 800; color: #000000; display: block; margin-bottom: 4px;">ESCOPO</label>
                        <select id="new-filter-channel" style="width: 100%;">
                            <option value="*">Global (*)</option>
                            <option value="inbox">Inbox</option>
                            <option value="notifications">Notifications</option>
                        </select>
                    </div>
                </div>
                <button class="btn-stamp btn-stamp-magenta" style="width: 100%;" onclick="addFilterSubmit()">CADASTRAR REGRA DE FILTRO</button>
            </div>

            <h3 style="font-family: 'Archivo Black', sans-serif; font-size: 0.95rem; text-transform: uppercase; color: #000000; margin-bottom: 0.8rem;">
                REGRAS ATIVAS
            </h3>
            <div id="manage-filters-list">Carregando filtros...</div>
        </div>
    </div>

    <script>
        function filterTimeline() {
            const query = document.getElementById('article-search').value.toLowerCase();
            const filterType = document.getElementById('article-filter').value;
            const items = document.querySelectorAll('#timeline .item-post');
            
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                const matchesSearch = text.includes(query);
                const isRead = item.classList.contains('read');
                
                let matchesFilter = true;
                if (filterType === 'unread' && isRead) matchesFilter = false;
                if (filterType === 'read' && !isRead) matchesFilter = false;
                
                item.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
            });
        }

        const ENDPOINT = "<?= $endpoint ?>";
        let currentChannel = "<?= htmlspecialchars($_GET['channel'] ?? 'inbox') ?>";
        window.timelineItems = [];

        function logout() {
            window.location.href = '/admin/config?action=logout';
        }

        async function api(action, method = 'GET', body = null) {
            let url = ENDPOINT;
            let options = { method, credentials: 'same-origin', headers: {} };

            if (method === 'GET') {
                url += '?action=' + action;
                if (body) {
                    for (const [key, val] of Object.entries(body)) {
                        url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(val);
                    }
                }
            } else {
                options.body = new URLSearchParams({ action, ...body });
                options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }

            const res = await fetch(url, options);
            if (res.status === 401) {
                logout();
                throw new Error("Unauthorized");
            }
            return await res.json();
        }

        window.onload = () => {
            loadChannelTitle();
            loadTimeline();
            initPushUI();
        };

        // Unblurring de Content Warning
        document.addEventListener('click', function(e) {
            if (e.target.tagName === 'IMG' && e.target.closest('.cw-fallback')) {
                e.target.classList.toggle('revealed');
            }
        });

        async function loadChannelTitle() {
            try {
                const data = await api('channels');
                const ch = data.channels.find(c => c.uid === currentChannel);
                if (ch) {
                    document.getElementById('current-channel-title').textContent = ch.name.toUpperCase();
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function loadTimeline() {
            const container = document.getElementById('timeline');
            container.innerHTML = '<p style="text-align: center; color: var(--text-muted); font-family: \'JetBrains Mono\', monospace; padding: 3rem;">CARREGANDO TIMELINE...</p>';

            try {
                const data = await api('timeline', 'GET', { channel: currentChannel });
                container.innerHTML = '';
                
                window.timelineItems = data.items || [];

                if (!window.timelineItems || window.timelineItems.length === 0) {
                    container.innerHTML = '<div class="card-lambe" style="text-align: center; padding: 3rem;"><p style="font-family: \'JetBrains Mono\', monospace; color: var(--text-muted); margin: 0;">NENHUM POST ENCONTRADO NESTE CANAL.</p></div>';
                    return;
                }

                window.timelineItems.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'item-post' + (item._is_read ? ' read' : '');
                    
                    let authorHtml = '';
                    if (item.author) {
                        authorHtml = `
                            <div class="item-author-info">
                                ${item.author.photo ? `<img src="${item.author.photo}" class="item-author-avatar" alt="Avatar">` : ''}
                                <div>
                                    <div class="item-author-name">${item.author.name}</div>
                                    <div class="item-date">${new Date(item.published).toLocaleString()}</div>
                                </div>
                            </div>
                        `;
                    } else {
                        authorHtml = `
                            <div class="item-author-info">
                                <div class="item-date">${new Date(item.published || Date.now()).toLocaleString()}</div>
                            </div>
                        `;
                    }

                    let ext = item._indieinabox || {};
                    let capabilities = ext.capabilities || ['reply', 'like', 'repost'];
                    let network = ext.network || 'rss';
                    let serverName = ext.origin_server || (item.url ? new URL(item.url).hostname : 'local');

                    let networkBadge = `<span class="badge-stamp badge-cyan">${network.toUpperCase()} // ${serverName}</span>`;

                    let buttonsHtml = `<a href="${item.url}" target="_blank" class="btn-stamp" style="font-size: 0.75rem; padding: 6px 12px;">↗ ORIGINAL</a>`;

                    // Interações nativas ActivityPub / Micropub
                    if (capabilities.includes('like')) {
                        buttonsHtml += `<button onclick="interactPostAP('like', '${item.url}')" class="btn-stamp btn-stamp-magenta" style="font-size: 0.75rem; padding: 6px 12px;" title="Like via ActivityPub">♥ LIKE</button>`;
                    } else if (capabilities.includes('local_like')) {
                        buttonsHtml += `<button onclick="interactPostMicropub('like', '${item.url}')" class="btn-stamp btn-stamp-cyan" style="font-size: 0.75rem; padding: 6px 12px;" title="Curtir no meu blog">♥ LIKE*</button>`;
                    }

                    if (capabilities.includes('repost')) {
                        buttonsHtml += `<button onclick="interactPostAP('repost', '${item.url}')" class="btn-stamp btn-stamp-cyan" style="font-size: 0.75rem; padding: 6px 12px;" title="Repost via ActivityPub">↻ REPOST</button>`;
                    }

                    if (capabilities.includes('reply')) {
                        buttonsHtml += `<button onclick="interactPostAP('reply', '${item.url}')" class="btn-stamp btn-stamp-yellow" style="font-size: 0.75rem; padding: 6px 12px;" title="Reply via ActivityPub">💬 REPLY</button>`;
                    }

                    // Compartilhar local no blog
                    buttonsHtml += `
                        <button onclick="interactPostMicropub('repost', '${item.url}')" class="btn-stamp" style="font-size: 0.75rem; padding: 6px 12px;" title="Publicar citação no próprio blog">✍ NO BLOG</button>
                    `;

                    // Enquete / Poll UI
                    let pollHtml = '';
                    if (ext.poll && ext.poll.options) {
                        let totalVotes = ext.poll.options.reduce((sum, opt) => sum + (opt.votes || 0), 0);
                        pollHtml = '<div style="margin: 1.25rem 0; background: #121316; border: 2px solid #000; padding: 1.25rem; box-shadow: 3px 3px 0 #000;">';
                        pollHtml += '<div style="font-family: \'JetBrains Mono\', monospace; font-size: 0.8rem; font-weight: 800; color: var(--tint-yellow); margin-bottom: 10px; text-transform: uppercase;">📊 ENQUETE INTERATIVA:</div>';
                        ext.poll.options.forEach(opt => {
                            let pct = totalVotes > 0 ? Math.round(((opt.votes || 0) / totalVotes) * 100) : 0;
                            let safeTitle = opt.title.replace(/'/g, "\\'");
                            pollHtml += `
                                <div style="margin-bottom: 10px; cursor: pointer;" onclick="interactPostAP('poll_vote', '${item.url}', '${safeTitle}')" title="Votar em '${opt.title}'">
                                    <div style="display: flex; justify-content: space-between; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; margin-bottom: 4px;">
                                        <span>${opt.title}</span>
                                        <span style="font-weight: 800; color: var(--tint-yellow);">${pct}% (${opt.votes || 0})</span>
                                    </div>
                                    <div style="width: 100%; background: #000; height: 18px; border: 1px solid #333; overflow: hidden;">
                                        <div style="width: ${pct}%; background: var(--tint-magenta); height: 100%; transition: width 0.5s ease-out;"></div>
                                    </div>
                                </div>
                            `;
                        });
                        pollHtml += `<div style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: var(--text-muted); text-align: right; margin-top: 8px;">Total de votos: ${totalVotes}</div></div>`;
                        pollHtml += '<style>.poll-fallback { display: none !important; }</style>';
                    }

                    div.innerHTML = `
                        <div class="item-post-header">
                            ${authorHtml}
                            <div style="display: flex; align-items: center; gap: 8px;">
                                ${networkBadge}
                                <button onclick="this.closest('.item-post').nextElementSibling?.scrollIntoView({behavior: 'smooth', block: 'start'})" class="btn-stamp" style="font-size: 0.7rem; padding: 4px 8px;" title="Próximo post">↓</button>
                            </div>
                        </div>
                        <div class="item-content">${item.content.html || item.content.text || ''}</div>
                        ${pollHtml}
                        <div class="item-actions-bar">
                            ${buttonsHtml}
                            ${(item.author && item.author.name) ? `<button onclick="quickMute('author', '${item.author.name.replace(/'/g, "\\'")}')" class="btn-stamp btn-stamp-red" style="font-size: 0.75rem; padding: 6px 12px; margin-left: 6px;">⊘ SILENCIAR AUTOR</button>` : ''}
                            ${!item._is_read ? `<button onclick="markRead('${item._id}')" class="btn-stamp" style="margin-left: auto; font-size: 0.75rem; padding: 6px 12px; background: var(--tint-green); color: #000;">✓ MARCAR LIDO</button>` : ''}
                        </div>
                    `;
                    container.appendChild(div);
                });
            } catch (err) {
                container.innerHTML = '<div class="card-lambe" style="text-align: center; border-color: var(--tint-red);"><p style="color: var(--tint-red); font-family: \'JetBrains Mono\';">FALHA AO CARREGAR TIMELINE.</p></div>';
            }
        }

        async function markRead(id) {
            try {
                await api('timeline', 'POST', { method: 'mark_read', channel: currentChannel, entry: id });
                loadTimeline();
            } catch (err) {
                alert("Falha ao marcar como lido");
            }
        }
        
        async function interactPostAP(action, targetUrl, prefilledContent = null) {
            let content = prefilledContent || '';
            if (action === 'reply' && !content) {
                content = prompt("Digite sua resposta nativa via ActivityPub:");
                if (!content) return;
            }

            try {
                const res = await api('interact', 'POST', {
                    interaction_type: action,
                    target_url: targetUrl,
                    content: content
                });
                if (res.success || res.activity_id) {
                    alert(action.toUpperCase() + " enfileirado com sucesso via ActivityPub!");
                } else {
                    alert("Falha ao interagir: " + (res.error_description || "Erro desconhecido"));
                }
            } catch (err) {
                console.error(err);
                alert("Falha ao enviar interação.");
            }
        }

        async function interactPostMicropub(action, targetUrl) {
            let content = '';
            let payload = {
                action: 'create',
                h: 'entry'
            };
            
            if (action === 'reply') {
                content = prompt("Digite sua resposta para publicar no seu blog:");
                if (!content) return;
                
                const item = window.timelineItems.find(i => i.url === targetUrl);
                if (item) {
                    let originalText = item.content.text || item.content.html || '';
                    originalText = originalText.replace(/<[^>]+>/g, '').trim();
                    if (originalText.length > 150) {
                        originalText = originalText.substring(0, 150) + '...';
                    }
                    content += "\n\n> " + originalText + "\n>\n> -- [" + (item.author ? item.author.name : "Post Original") + "](" + item.url + ")";
                }

                payload['in-reply-to'] = targetUrl;
                payload.content = content;
                payload['mp-slug'] = 'reply-' + Date.now();
            } else if (action === 'like') {
                payload['like-of'] = targetUrl;
                payload['mp-slug'] = 'like-' + Date.now();
            } else if (action === 'repost') {
                payload['repost-of'] = targetUrl;
                payload['mp-slug'] = 'repost-' + Date.now();
            }

            try {
                const formData = new URLSearchParams(payload);
                const res = await fetch('/micropub', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                });
                
                if (res.ok || res.status === 202) {
                    alert(action.toUpperCase() + " publicado com sucesso no seu blog!");
                } else {
                    const text = await res.text();
                    alert("Falha ao publicar " + action + ": " + text);
                }
            } catch (err) {
                console.error(err);
                alert("Falha ao enviar interação.");
            }
        }

        async function fetchFeeds() {
            const btn = document.querySelector('button[onclick="fetchFeeds()"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = 'SINCRONIZANDO... ⏳';
                btn.style.opacity = '0.7';
                btn.style.cursor = 'wait';
            }
            try {
                await api('fetch', 'POST');
                setTimeout(async () => {
                    await loadTimeline();
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '⚡ SINCRONIZAR';
                        btn.style.opacity = '1';
                        btn.style.cursor = 'pointer';
                    }
                }, 1000);
            } catch (err) {
                console.error(err);
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '⚡ SINCRONIZAR';
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                }
                alert('Erro ao sincronizar feeds: ' + err.message);
            }
        }

        async function addFeed() {
            if (!currentChannel) return;
            const url = prompt("Digite a URL do feed (RSS, Atom, JSON, Twtxt ou perfil Fediverse/ActivityPub):");
            if (!url) return;
            
            try {
                const res = await api('follow', 'POST', { channel: currentChannel, url: url });
                if (res.error) {
                    alert("Erro: " + res.error_description);
                } else {
                    alert("Assinatura confirmada! Sincronizando feeds em segundo plano...");
                    fetchFeeds();
                }
            } catch (err) {
                alert("Falha ao assinar feed. Consulte o console para detalhes.");
                console.error(err);
            }
        }

        async function manageFeeds() {
            if (!currentChannel) return;
            document.getElementById('manage-feeds-modal').style.display = 'flex';
            const listContainer = document.getElementById('manage-feeds-list');
            listContainer.innerHTML = '<p style="font-family: \'JetBrains Mono\';">Carregando fontes...</p>';
            try {
                const res = await api('follow', 'GET', { channel: currentChannel });
                if (res.items && res.items.length > 0) {
                    let html = '<ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px;">';
                    res.items.forEach(feed => {
                        let typeBadge = `<span class="badge-stamp badge-yellow">${feed.feed_type ? feed.feed_type.toUpperCase() : 'RSS'}</span>`;
                        let photoHtml = feed.photo ? `<img src="${feed.photo}" style="width: 28px; height: 28px; border: 1px solid #000; margin-right: 8px; vertical-align: middle;">` : '';
                        let displayName = feed.name ? `<strong>${feed.name}</strong><br><small style="font-family: 'JetBrains Mono'; opacity: 0.7;">${feed.url}</small>` : `<span style="font-family: 'JetBrains Mono';">${feed.url}</span>`;
                        
                        html += `
                            <li style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--lambe-paper, #f4efe6); border: 2px solid #000; box-shadow: 2px 2px 0 #000; color: #000;">
                                <div style="word-break: break-all; margin-right: 1rem;">
                                    <div style="margin-bottom: 4px;">${photoHtml}${typeBadge}</div>
                                    <div>${displayName}</div>
                                </div>
                                <button class="btn-stamp btn-stamp-red" style="font-size: 0.75rem; padding: 6px 12px;" onclick="unfollowFeed('${feed.url}')">DESASSINAR</button>
                            </li>
                        `;
                    });
                    html += '</ul>';
                    listContainer.innerHTML = html;
                } else {
                    listContainer.innerHTML = '<p style="font-family: \'JetBrains Mono\';">Nenhum feed assinado neste canal.</p>';
                }
            } catch (err) {
                listContainer.innerHTML = '<p style="color: var(--tint-red); font-family: \'JetBrains Mono\';">Falha ao carregar lista de feeds.</p>';
                console.error(err);
            }
        }

        function closeManageFeeds() {
            document.getElementById('manage-feeds-modal').style.display = 'none';
        }

        async function unfollowFeed(url) {
            if (!confirm('Deseja realmente remover esta assinatura?')) return;
            try {
                const res = await api('unfollow', 'POST', { channel: currentChannel, url: url });
                if (res.error) {
                    alert("Erro: " + res.error_description);
                } else {
                    manageFeeds();
                }
            } catch (err) {
                alert("Falha ao desassinar feed.");
                console.error(err);
            }
        }

        async function manageFilters() {
            document.getElementById('manage-filters-modal').style.display = 'flex';
            loadFiltersList();
        }

        function closeManageFilters() {
            document.getElementById('manage-filters-modal').style.display = 'none';
        }

        async function loadFiltersList() {
            const listContainer = document.getElementById('manage-filters-list');
            listContainer.innerHTML = '<p style="font-family: \'JetBrains Mono\';">Carregando regras ativas...</p>';
            try {
                const res = await api('filters', 'GET', {});
                if (res.filters && res.filters.length > 0) {
                    let html = '<ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px;">';
                    res.filters.forEach(f => {
                        let actionBadgeClass = f.action === 'drop' ? 'badge-red' : (f.action === 'mute' ? 'badge-yellow' : 'badge-cyan');
                        let actionBadge = `<span class="badge-stamp ${actionBadgeClass}">${f.action.toUpperCase()}</span>`;
                        let typeBadge = `<span class="badge-stamp">${f.filter_type}</span>`;
                        let channelBadge = `<span style="font-family: 'JetBrains Mono'; font-size: 0.72rem; opacity: 0.6;">[canal: ${f.channel_uid}]</span>`;

                        html += `
                            <li style="display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 1rem; background: var(--lambe-paper, #f4efe6); border: 2px solid #000; box-shadow: 2px 2px 0 #000; color: #000;">
                                <div>
                                    ${actionBadge} ${typeBadge} <strong style="font-family: 'JetBrains Mono';">${f.content}</strong> ${channelBadge}
                                </div>
                                <button class="btn-stamp btn-stamp-red" style="font-size: 0.7rem; padding: 4px 8px;" onclick="deleteFilterRule(${f.id})">EXCLUIR</button>
                            </li>
                        `;
                    });
                    html += '</ul>';
                    listContainer.innerHTML = html;
                } else {
                    listContainer.innerHTML = '<p style="color: var(--text-muted); font-family: \'JetBrains Mono\';">Nenhuma regra de moderação ativa.</p>';
                }
            } catch (err) {
                listContainer.innerHTML = '<p style="color: var(--tint-red); font-family: \'JetBrains Mono\';">Falha ao carregar regras.</p>';
                console.error(err);
            }
        }

        async function addFilterSubmit() {
            const type = document.getElementById('new-filter-type').value;
            const action = document.getElementById('new-filter-action').value;
            const content = document.getElementById('new-filter-content').value.trim();
            const channel = document.getElementById('new-filter-channel').value;

            if (!content) {
                alert('Informe um padrão ou termo para filtrar.');
                return;
            }

            try {
                const res = await api('filters', 'POST', {
                    method: 'create',
                    filter_type: type,
                    filter_action: action,
                    content: content,
                    channel: channel
                });
                if (res.error) {
                    alert('Erro: ' + res.error_description);
                } else {
                    document.getElementById('new-filter-content').value = '';
                    loadFiltersList();
                    loadTimeline();
                }
            } catch (err) {
                alert('Falha ao adicionar regra de filtro.');
                console.error(err);
            }
        }

        async function deleteFilterRule(id) {
            if (!confirm('Deseja excluir esta regra de moderação?')) return;
            try {
                const res = await api('filters', 'POST', { method: 'delete', id: id });
                if (res.error) {
                    alert('Erro: ' + res.error_description);
                } else {
                    loadFiltersList();
                    loadTimeline();
                }
            } catch (err) {
                alert('Falha ao excluir regra de filtro.');
                console.error(err);
            }
        }

        async function quickMute(type, target) {
            if (!target) return;
            if (!confirm('Silenciar ' + type + ' "' + target + '" da timeline?')) return;
            try {
                const res = await api('filters', 'POST', {
                    method: 'create',
                    filter_type: type,
                    filter_action: 'mute',
                    content: target,
                    channel: '*'
                });
                if (res.error) {
                    alert('Erro: ' + res.error_description);
                } else {
                    alert('Silenciado "' + target + '"! Atualizando timeline...');
                    loadTimeline();
                }
            } catch (err) {
                alert('Falha ao silenciar ' + type);
                console.error(err);
            }
        }

        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        async function initPushUI() {
            const btn = document.getElementById('push-toggle-btn');
            if (!btn || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                if (btn) btn.style.display = 'none';
                return;
            }

            try {
                const reg = await navigator.serviceWorker.register('/sw.js');
                const sub = await reg.pushManager.getSubscription();
                updatePushBtnState(sub !== null);
            } catch (e) {
                console.warn('Push registration check failed:', e);
            }
        }

        function updatePushBtnState(isSubscribed) {
            const btn = document.getElementById('push-toggle-btn');
            if (!btn) return;
            if (isSubscribed) {
                btn.textContent = '🔕 PUSH ATIVO';
                btn.className = 'btn-stamp btn-stamp-red';
            } else {
                btn.textContent = '🔔 ATIVAR PUSH';
                btn.className = 'btn-stamp';
            }
        }

        async function togglePushNotifications() {
            const btn = document.getElementById('push-toggle-btn');
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                alert('Notificações push não são suportadas neste navegador.');
                return;
            }

            try {
                const reg = await navigator.serviceWorker.ready;
                let sub = await reg.pushManager.getSubscription();

                if (sub) {
                    const endpoint = sub.endpoint;
                    await sub.unsubscribe();
                    await api('push_unsubscribe', 'POST', { endpoint });
                    updatePushBtnState(false);
                } else {
                    const permission = await Notification.requestPermission();
                    if (permission !== 'granted') {
                        alert('Permissão de notificação negada.');
                        return;
                    }

                    const keyData = await api('push_key');
                    if (!keyData || !keyData.publicKey) {
                        alert('Chave pública VAPID não encontrada no servidor.');
                        return;
                    }

                    const convertedVapidKey = urlBase64ToUint8Array(keyData.publicKey);
                    sub = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: convertedVapidKey
                    });

                    const subJson = sub.toJSON();
                    const p256dh = subJson.keys?.p256dh || '';
                    const auth = subJson.keys?.auth || '';

                    await api('push_subscribe', 'POST', {
                        endpoint: sub.endpoint,
                        p256dh: p256dh,
                        auth: auth
                    });

                    updatePushBtnState(true);
                }
            } catch (err) {
                console.error('Erro ao alternar push notifications:', err);
                alert('Falha na inscrição push: ' + (err.message || err));
            }
        }
    </script>
        <?php
        $inner = (string) ob_get_clean();
        return AdminLayoutView::render($inner, 'microsub', $fqdnClean);
    }
}
