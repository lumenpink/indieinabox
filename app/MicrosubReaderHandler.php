<?php

declare(strict_types=1);

namespace Indieinabox;

/**
 * Class MicrosubReaderHandler
 */
class MicrosubReaderHandler
{
    /**
     * @var \Indieinabox\Site
     */
    private Site $site;

    /**
     * Initializes the MicrosubReaderHandler.
     *
     * @param \Indieinabox\Site $site Global site configuration and environment.
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Handles requests for the Microsub reader interface.
     * Enforces authentication and routes to specific reader actions or views.
     *
     * @return void
     */
    public function handle(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(43200);
            session_start();
        }

        // Require authentication
        if (empty($_SESSION['admin_authenticated'])) {
            $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
            header('Location: ' . $fqdn . '/admin/config');
            return;
        }

        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $endpoint = $fqdn . '/microsub';
        
        $activeTab = 'microsub';
        $adminLayoutPath = dirname(__DIR__) . '/resources/views/admin_layout.php';
        
        ob_start();
        ?>
    <style>
        :root {
            --glass-bg: rgba(17, 24, 39, 0.7);
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-blur: blur(20px);
            --accent: #eccb00;
            --accent-glow: rgba(236, 203, 0, 0.35);
            --accent-gradient: var(--accent);
            --text-main: #f9fafb;
            --text-muted: #9ca3af;
            --bg-gradient: transparent;
        }

        .microsub-wrapper {
            font-family: 'Outfit', system-ui, sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .microsub-wrapper .glass {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7),
                        0 0 50px rgba(236, 203, 0, 0.03);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        /* Login View */
        .login-prompt {
            text-align: center;
            padding: 4rem;
            max-width: 400px;
            width: 90%;
        }

        .login-prompt h1 {
            background: var(--accent-gradient);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-top: 0;
        }

        .login-prompt input {
            width: 100%;
            padding: 1rem;
            margin: 1.5rem 0;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: #fff;
            font-family: inherit;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .login-prompt input:focus {
            border-color: var(--accent);
        }

        .btn {
            background: linear-gradient(135deg, #eccb00 0%, #d8b600 100%);
            color: #030712;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px var(--accent-glow);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px var(--accent-glow);
            background: linear-gradient(135deg, #fce029 0%, #eccb00 100%);
        }

        #error-msg {
            color: #ff6b6b;
            margin-top: 1rem;
            font-size: 0.9rem;
        }

        /* App View */
        #reader-view {
            width: 95%;
            max-width: 1200px;
            height: 90vh;
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        .sidebar {
            display: none;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 3rem;
        }

        .sidebar-header h2 {
            margin: 0;
            font-weight: 600;
            background: var(--accent-gradient);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .channels-list {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }

        .channel-item {
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s ease, padding-left 0.2s ease;
            color: var(--text-muted);
            font-weight: 600;
        }

        .channel-item:hover, .channel-item.active {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-main);
            padding-left: 1.5rem;
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 2rem;
            border-top: 1px solid var(--glass-border);
        }

        .sidebar-footer button {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--glass-border);
            width: 100%;
        }
        .sidebar-footer button:hover {
            color: #fff;
            border-color: #fff;
        }

        .main-content {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--glass-border);
            margin-bottom: 2rem;
        }

        .timeline-header h1 {
            margin: 0;
            font-size: 1.5rem;
        }

        #timeline {
            flex-grow: 1;
            overflow-y: auto;
            padding-right: 1rem;
            scrollbar-width: thin;
            scrollbar-color: var(--glass-border) transparent;
        }

        #timeline::-webkit-scrollbar {
            width: 6px;
        }
        #timeline::-webkit-scrollbar-thumb {
            background-color: var(--glass-border);
            border-radius: 3px;
        }

        .item {
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease, border-color 0.2s ease;
            animation: fadeIn 0.4s ease-out forwards;
        }

        .item:hover {
            border-color: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .item.read {
            opacity: 0.5;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .item-author {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .item-author img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--glass-border);
        }

        .item-author .name {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .item-author .date {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .item-content {
            line-height: 1.7;
            font-size: 1rem;
            color: #dcdcdc;
        }

        .item-content a {
            color: #cf8bf3;
            text-decoration: none;
        }
        .item-content a:hover {
            text-decoration: underline;
        }

        .item-content img {
            max-width: 100%;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .item-actions {
            margin-top: 1.5rem;
            display: flex;
            gap: 1rem;
            border-top: 1px solid var(--glass-border);
            padding-top: 1rem;
        }

        .item-actions a, .item-actions button {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: inherit;
            text-decoration: none;
            transition: color 0.2s ease;
            padding: 0;
        }

        .item-actions button:hover, .item-actions a:hover {
            color: #cf8bf3;
        }

        .cw-fallback details {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.3);
            border-radius: 8px;
            padding: 0.5rem;
            margin-bottom: 1rem;
        }
        .cw-fallback summary {
            cursor: pointer;
            font-weight: 600;
            color: #ff6b6b;
            padding: 0.5rem;
            outline: none;
            user-select: none;
        }
        .cw-fallback details[open] summary {
            border-bottom: 1px solid rgba(255, 107, 107, 0.3);
            margin-bottom: 0.5rem;
        }
        .cw-fallback img {
            filter: blur(35px);
            cursor: pointer;
            transition: filter 0.4s ease;
        }
        .cw-fallback img.revealed {
            filter: none;
        }

        @media (max-width: 768px) {
            #reader-view {
                grid-template-columns: 1fr;
                height: 100vh;
                width: 100%;
                border-radius: 0;
            }
            .sidebar {
                display: none; /* In a real app, we'd add a hamburger menu */
            }
        }
    </style>
    <div class="microsub-wrapper">
    <div id="reader-view" style="display: none;">


        <main class="main-content">
            <div class="timeline-header">
                <h1 id="current-channel-title">Inbox</h1>
                <div style="display: flex; gap: 10px; align-items: center; flex: 1; margin: 0 20px;">
                    <input type="text" id="article-search" placeholder="Search / Filtrar..." style="flex: 1; padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--glass-border); background: rgba(0,0,0,0.2); color: white; outline: none;" oninput="filterTimeline()">
                    <select id="article-filter" style="padding: 0.5rem; border-radius: 8px; border: 1px solid var(--glass-border); background: rgba(0,0,0,0.2); color: white; outline: none;" onchange="filterTimeline()">
                        <option value="all">Todos</option>
                        <option value="unread">Não lidos</option>
                        <option value="read">Lidos</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn" onclick="addFeed()">Add Feed</button>
                    <button class="btn" onclick="manageFeeds()">Manage Feeds</button>
                    <button class="btn" onclick="fetchFeeds()">Sync Feeds</button>
                </div>
            </div>
            <div id="timeline">
                <p style="text-align: center; color: var(--text-muted); margin-top: 2rem;">Loading...</p>
            </div>
        </main>
    </div>

    <div id="manage-feeds-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: #111827; padding: 2rem; border-radius: 12px; width: 90%; max-width: 600px; max-height: 80vh; overflow-y: auto; border: 1px solid var(--glass-border); box-shadow: 0 25px 50px rgba(0,0,0,0.5);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="margin: 0;">Manage Feeds</h2>
                <button class="btn" style="padding: 0.5rem 1rem;" onclick="closeManageFeeds()">Close</button>
            </div>
            <div id="manage-feeds-list">Loading...</div>
        </div>
    </div>

    <script>
        function filterTimeline() {
            const query = document.getElementById('article-search').value.toLowerCase();
            const filterType = document.getElementById('article-filter').value;
            const items = document.querySelectorAll('#timeline .item');
            
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
            document.getElementById('reader-view').style.display = 'grid';
            loadChannelTitle();
            loadTimeline();
        };

        // Handle unblurring of Content Warning images
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
                    document.getElementById('current-channel-title').textContent = ch.name;
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function loadTimeline() {
            const container = document.getElementById('timeline');
            container.innerHTML = '<p style="text-align: center; color: var(--text-muted); margin-top: 2rem;">Loading...</p>';

            try {
                const data = await api('timeline', 'GET', { channel: currentChannel });
                container.innerHTML = '';
                
                window.timelineItems = data.items || [];

                if (!window.timelineItems || window.timelineItems.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: var(--text-muted); margin-top: 2rem;">No items found.</p>';
                    return;
                }

                window.timelineItems.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'item glass' + (item._is_read ? ' read' : '');
                    
                    let authorHtml = '';
                    if (item.author) {
                        authorHtml = `
                            <div class="item-author" style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    ${item.author.photo ? `<img src="${item.author.photo}">` : ''}
                                    <div>
                                        <div class="name">${item.author.name}</div>
                                        <div class="date">${new Date(item.published).toLocaleString()}</div>
                                    </div>
                                </div>
                                <button onclick="this.closest('.item').nextElementSibling?.scrollIntoView({behavior: 'smooth', block: 'start'})" class="btn" style="padding: 4px 8px; font-size: 0.8rem; background: transparent; border: 1px solid var(--glass-border); color: var(--text-muted);" title="Skip to next post">Next ↓</button>
                            </div>
                        `;
                    } else {
                        authorHtml = `
                            <div class="item-author" style="display: flex; justify-content: flex-end;">
                                <button onclick="this.closest('.item').nextElementSibling?.scrollIntoView({behavior: 'smooth', block: 'start'})" class="btn" style="padding: 4px 8px; font-size: 0.8rem; background: transparent; border: 1px solid var(--glass-border); color: var(--text-muted);" title="Skip to next post">Next ↓</button>
                            </div>
                        `;
                    }

                    let ext = item._indieinabox || {};
                    let capabilities = ext.capabilities || ['reply', 'like', 'repost'];
                    let network = ext.network || 'rss';
                    let serverName = ext.origin_server || new URL(item.url || 'http://localhost').hostname;

                    let networkBadge = `<span style="font-size: 0.7rem; padding: 2px 6px; background: rgba(255,255,255,0.1); border: 1px solid var(--glass-border); border-radius: 4px; margin-left: auto;">${network.toUpperCase()} • ${serverName}</span>`;

                    let buttonsHtml = `<a href="${item.url}" target="_blank">View Original</a>`;

                    // Generate native AP interaction buttons if supported
                    if (capabilities.includes('like')) {
                        buttonsHtml += `<button onclick="interactPostAP('like', '${item.url}')" title="Like">Like</button>`;
                    } else if (capabilities.includes('local_like')) {
                        buttonsHtml += `<button onclick="interactPostMicropub('like', '${item.url}')" title="Like on your blog only" style="color: #99ccff;">Like*</button>`;
                    }

                    if (capabilities.includes('repost')) {
                        buttonsHtml += `<button onclick="interactPostAP('repost', '${item.url}')" title="Repost">Repost</button>`;
                    }

                    if (capabilities.includes('reply')) {
                        buttonsHtml += `<button onclick="interactPostAP('reply', '${item.url}')" title="Reply">Reply</button>`;
                    }

                    if (capabilities.includes('poll_vote')) {
                        buttonsHtml += `<button onclick="alert('Poll voting UI coming soon!')" title="Vote in poll" style="color: var(--accent);">Vote</button>`;
                    }

                    // Always allow local blog shares
                    buttonsHtml += `
                        <span style="border-left: 1px solid var(--glass-border); margin: 0 8px; height: 16px; display: inline-block; vertical-align: middle;"></span>
                        <button onclick="interactPostMicropub('repost', '${item.url}')" title="Create a local post on your blog">Share on Blog</button>
                    `;

                    // Generate Poll UI
                    let pollHtml = '';
                    if (ext.poll && ext.poll.options) {
                        let totalVotes = ext.poll.options.reduce((sum, opt) => sum + (opt.votes || 0), 0);
                        pollHtml = '<div class="native-poll" style="margin-top: 1rem; background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--glass-border);">';
                        ext.poll.options.forEach(opt => {
                            let pct = totalVotes > 0 ? Math.round(((opt.votes || 0) / totalVotes) * 100) : 0;
                            // Escape single quotes for JS onClick
                            let safeTitle = opt.title.replace(/'/g, "\\'");
                            pollHtml += `
                                <div style="margin-bottom: 1rem; cursor: pointer; position: relative;" onclick="interactPostAP('poll_vote', '${item.url}', '${safeTitle}')" title="Click to vote for '${opt.title}'">
                                    <div style="display: flex; justify-content: space-between; font-size: 0.95rem; margin-bottom: 0.4rem; font-weight: 500;">
                                        <span style="z-index: 2; text-shadow: 1px 1px 2px rgba(0,0,0,0.8);">${opt.title}</span>
                                        <span style="z-index: 2; color: #ddd;">${pct}%</span>
                                    </div>
                                    <div style="width: 100%; background: rgba(255,255,255,0.05); height: 28px; border-radius: 6px; overflow: hidden; position: relative; border: 1px solid rgba(255,255,255,0.1);">
                                        <div style="width: ${pct}%; background: rgba(236, 203, 0, 0.3); height: 100%; transition: width 0.8s ease-out;"></div>
                                    </div>
                                </div>
                            `;
                        });
                        pollHtml += `<div style="font-size: 0.85rem; color: var(--text-muted); text-align: right; margin-top: 0.5rem;">Total votes: ${totalVotes}</div></div>`;
                        // Inject CSS to hide the fallback for this specific item
                        pollHtml += '<style>.poll-fallback { display: none !important; }</style>';
                    }

                    div.innerHTML = `
                        <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                            ${networkBadge}
                        </div>
                        ${authorHtml}
                        <div class="item-content">${item.content.html || item.content.text || ''}</div>
                        ${pollHtml}
                        <div class="item-actions">
                            ${buttonsHtml}
                            ${!item._is_read ? `<button onclick="markRead('${item._id}')" style="margin-left: auto;">Mark Read</button>` : ''}
                        </div>
                    `;
                    container.appendChild(div);
                });
            } catch (err) {
                container.innerHTML = '<p style="text-align: center; color: #ff6b6b; margin-top: 2rem;">Failed to load timeline.</p>';
            }
        }

        async function markRead(id) {
            try {
                await api('timeline', 'POST', { method: 'mark_read', channel: currentChannel, entry: id });
                loadTimeline();
            } catch (err) {
                alert("Failed to mark as read");
            }
        }
        
        async function interactPostAP(action, targetUrl, prefilledContent = null) {
            let content = prefilledContent || '';
            if (action === 'reply' && !content) {
                content = prompt("Enter your native ActivityPub reply:");
                if (!content) return;
            }

            try {
                const res = await api('interact', 'POST', {
                    interaction_type: action,
                    target_url: targetUrl,
                    content: content
                });
                if (res.success || res.activity_id) {
                    alert(action.charAt(0).toUpperCase() + action.slice(1) + " queued via ActivityPub!");
                } else {
                    alert("Failed to " + action + ": " + (res.error_description || "Unknown error"));
                }
            } catch (err) {
                console.error(err);
                alert("Failed to send interaction");
            }
        }

        async function interactPostMicropub(action, targetUrl) {
            let content = '';
            let payload = {
                action: 'create',
                h: 'entry'
            };
            
            if (action === 'reply') {
                content = prompt("Enter your reply:");
                if (!content) return;
                
                // Find item context
                const item = window.timelineItems.find(i => i.url === targetUrl);
                if (item) {
                    let originalText = item.content.text || item.content.html || '';
                    // Strip HTML if it's HTML
                    originalText = originalText.replace(/<[^>]+>/g, '').trim();
                    // Truncate to 150 chars
                    if (originalText.length > 150) {
                        originalText = originalText.substring(0, 150) + '...';
                    }
                    
                    content += "\n\n> " + originalText + "\n>\n> -- [" + (item.author ? item.author.name : "Original Post") + "](" + item.url + ")";
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
                    alert(action.charAt(0).toUpperCase() + action.slice(1) + " sent successfully!");
                } else {
                    const text = await res.text();
                    alert("Failed to " + action + ": " + text);
                }
            } catch (err) {
                console.error(err);
                alert("Failed to send interaction");
            }
        }

        async function fetchFeeds() {
            const btn = document.querySelector('button[onclick="fetchFeeds()"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = 'Syncing... ⏳';
                btn.style.opacity = '0.7';
                btn.style.cursor = 'wait';
            }
            try {
                await api('fetch', 'POST');
                setTimeout(async () => {
                    await loadTimeline();
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = 'Sync Feeds';
                        btn.style.opacity = '1';
                        btn.style.cursor = 'pointer';
                    }
                }, 1000);
            } catch (err) {
                console.error(err);
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'Sync Feeds';
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                }
                alert('Error syncing feeds: ' + err.message);
            }
        }

        async function addFeed() {
            if (!currentChannel) return;
            const url = prompt("Enter feed URL (RSS, Atom, JSON, Twtxt, or Fediverse account):");
            if (!url) return;
            
            try {
                const res = await api('follow', 'POST', { channel: currentChannel, url: url });
                if (res.error) {
                    alert("Error: " + res.error_description);
                } else {
                    alert("Subscribed successfully! Syncing feeds in the background...");
                    fetchFeeds();
                }
            } catch (err) {
                alert("Failed to subscribe to feed. See console for details.");
                console.error(err);
            }
        }

        async function manageFeeds() {
            if (!currentChannel) return;
            document.getElementById('manage-feeds-modal').style.display = 'flex';
            const listContainer = document.getElementById('manage-feeds-list');
            listContainer.innerHTML = 'Loading...';
            try {
                const res = await api('follow', 'GET', { channel: currentChannel });
                if (res.items && res.items.length > 0) {
                    let html = '<ul style="list-style: none; padding: 0; margin: 0;">';
                    res.items.forEach(feed => {
                        let typeBadge = `<span style="font-size: 0.7rem; padding: 2px 6px; background: var(--primary); color: white; border-radius: 4px; margin-right: 8px;">${feed.feed_type ? feed.feed_type.toUpperCase() : 'RSS'}</span>`;
                        let photoHtml = feed.photo ? `<img src="${feed.photo}" style="width: 24px; height: 24px; border-radius: 50%; margin-right: 8px; vertical-align: middle;">` : '';
                        let displayName = feed.name ? `<strong>${feed.name}</strong><br><small style="opacity: 0.7;">${feed.url}</small>` : feed.url;
                        
                        html += `
                            <li style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid var(--glass-border);">
                                <div style="word-break: break-all; margin-right: 1rem;">
                                    <div>${photoHtml}${typeBadge}</div>
                                    <div style="margin-top: 4px;">${displayName}</div>
                                </div>
                                <button class="btn" style="padding: 0.5rem 1rem; font-size: 0.9rem; background: #ff4b4b; color: white;" onclick="unfollowFeed('${feed.url}')">Unfollow</button>
                            </li>
                        `;
                    });
                    html += '</ul>';
                    listContainer.innerHTML = html;
                } else {
                    listContainer.innerHTML = '<p>No feeds subscribed in this channel.</p>';
                }
            } catch (err) {
                listContainer.innerHTML = '<p style="color: red;">Failed to load feeds.</p>';
                console.error(err);
            }
        }

        function closeManageFeeds() {
            document.getElementById('manage-feeds-modal').style.display = 'none';
        }

        async function unfollowFeed(url) {
            if (!confirm('Are you sure you want to unfollow this feed?')) return;
            try {
                const res = await api('unfollow', 'POST', { channel: currentChannel, url: url });
                if (res.error) {
                    alert("Error: " + res.error_description);
                } else {
                    manageFeeds(); // refresh list
                }
            } catch (err) {
                alert("Failed to unfollow feed. See console for details.");
                console.error(err);
            }
        }
    </script>
</div>
<?php
        $content = ob_get_clean();
        \Indieinabox\ThemeManager::loadView($adminLayoutPath, [
            'content' => $content,
            'activeTab' => $activeTab,
            'fqdn' => $fqdn
        ]);
    }
}
