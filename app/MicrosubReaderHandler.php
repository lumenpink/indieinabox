<?php

declare(strict_types=1);

namespace Indieinabox;

/**
 * Class MicrosubReaderHandler
 */
class MicrosubReaderHandler
{
    /**
     * @var Indieinabox\Site
     */
    private Site $site;

    /**
     * Method __construct
     * @param Indieinabox\Site $site
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Method handleRequest
     * @return void
     */
    public function handleRequest(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $endpoint = $fqdn . '/microsub';
        
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexus - Microsub Reader</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
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
            --bg-gradient: linear-gradient(135deg, #090d16 0%, #111827 50%, #1e1b4b 100%);
        }

        body {
            font-family: 'Outfit', system-ui, sans-serif;
            background: var(--bg-gradient);
            background-attachment: fixed;
            color: var(--text-main);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .glass {
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
            grid-template-columns: 280px 1fr;
            gap: 2rem;
        }

        .sidebar {
            padding: 2rem;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
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
</head>
<body>

    <div id="login-view" class="login-prompt glass" style="display: none;">
        <h1>Nexus Reader</h1>
        <p>Authenticate using your bearer token.</p>
        <input type="password" id="token-input" placeholder="Bearer Token">
        <button class="btn" onclick="login()">Connect</button>
        <div id="error-msg"></div>
    </div>

    <div id="reader-view" style="display: none;">
        <aside class="sidebar glass">
            <div class="sidebar-header">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="url(#grad)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <defs>
                        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#a770ef" />
                            <stop offset="100%" style="stop-color:#fdb99b" />
                        </linearGradient>
                    </defs>
                    <path d="M4 11a9 9 0 0 1 9 9"></path>
                    <path d="M4 4a16 16 0 0 1 16 16"></path>
                    <circle cx="5" cy="19" r="1"></circle>
                </svg>
                <h2>Nexus</h2>
            </div>
            
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem; text-transform: uppercase; font-weight: 600;">Channels</div>
            <ul class="channels-list" id="channels-list">
                <!-- Channels injected here -->
            </ul>

            <div class="sidebar-footer">
                <button class="btn" onclick="logout()">Disconnect</button>
            </div>
        </aside>

        <main class="main-content">
            <div class="timeline-header">
                <h1 id="current-channel-title">Inbox</h1>
                <button class="btn" onclick="fetchFeeds()">Sync Feeds</button>
            </div>
            <div id="timeline">
                <p style="text-align: center; color: var(--text-muted); margin-top: 2rem;">Loading...</p>
            </div>
        </main>
    </div>

    <script>
        const ENDPOINT = "{$endpoint}";
        let token = localStorage.getItem('microsub_token');
        let currentChannel = 'inbox';

        window.onload = () => {
            if (token) {
                document.getElementById('reader-view').style.display = 'grid';
                loadChannels();
            } else {
                document.getElementById('login-view').style.display = 'block';
            }
        };

        function login() {
            const input = document.getElementById('token-input').value.trim();
            if (input) {
                token = input;
                localStorage.setItem('microsub_token', token);
                document.getElementById('login-view').style.display = 'none';
                document.getElementById('reader-view').style.display = 'grid';
                loadChannels();
            }
        }

        function logout() {
            localStorage.removeItem('microsub_token');
            location.reload();
        }

        async function api(action, method = 'GET', body = null) {
            const headers = {
                'Authorization': 'Bearer ' + token
            };
            let url = ENDPOINT;
            let options = { method, headers };

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

        async function loadChannels() {
            try {
                const data = await api('channels');
                const list = document.getElementById('channels-list');
                list.innerHTML = '';
                
                let first = true;
                data.channels.forEach(ch => {
                    const li = document.createElement('li');
                    li.className = 'channel-item' + (first ? ' active' : '');
                    li.textContent = ch.name;
                    li.onclick = () => {
                        document.querySelectorAll('.channel-item').forEach(el => el.classList.remove('active'));
                        li.classList.add('active');
                        currentChannel = ch.uid;
                        document.getElementById('current-channel-title').textContent = ch.name;
                        loadTimeline();
                    };
                    list.appendChild(li);
                    
                    if (first) {
                        currentChannel = ch.uid;
                        document.getElementById('current-channel-title').textContent = ch.name;
                        first = false;
                    }
                });
                
                if (!first) {
                    loadTimeline();
                }
            } catch (err) {
                document.getElementById('error-msg').textContent = err.message;
            }
        }

        async function loadTimeline() {
            const container = document.getElementById('timeline');
            container.innerHTML = '<p style="text-align: center; color: var(--text-muted); margin-top: 2rem;">Loading...</p>';

            try {
                const data = await api('timeline', 'GET', { channel: currentChannel });
                container.innerHTML = '';
                
                if (!data.items || data.items.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: var(--text-muted); margin-top: 2rem;">No items found.</p>';
                    return;
                }

                data.items.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'item glass' + (item._is_read ? ' read' : '');
                    
                    let authorHtml = '';
                    if (item.author) {
                        authorHtml = `
                            <div class="item-author">
                                \${item.author.photo ? `<img src="\${item.author.photo}">` : ''}
                                <div>
                                    <div class="name">\${item.author.name}</div>
                                    <div class="date">\${new Date(item.published).toLocaleString()}</div>
                                </div>
                            </div>
                        `;
                    }

                    div.innerHTML = `
                        \${authorHtml}
                        <div class="item-content">\${item.content.html || item.content.text || ''}</div>
                        <div class="item-actions">
                            <a href="\${item.url}" target="_blank">View Original</a>
                            \${!item._is_read ? `<button onclick="markRead('\${item._id}')">Mark Read</button>` : ''}
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

        async function fetchFeeds() {
            try {
                await api('fetch', 'POST');
                setTimeout(loadTimeline, 1000);
            } catch (err) {
                console.error(err);
            }
        }
    </script>
</body>
</html>

HTML;
    }
}
