<?php

declare(strict_types=1);

namespace Indieinabox;

class MicrosubReaderHandler
{
    private Site $site;

    public function __construct(Site $site)
    {
        $this->site = $site;
    }

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
    <title>Microsub Reader</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body {
            font-family: var(--font-family, system-ui, sans-serif);
            background: var(--bg-color, #1a1a1a);
            color: var(--text-color, #e0e0e0);
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color, #333);
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        .header h1 {
            margin: 0;
            background: linear-gradient(90deg, #ffffff, #eccb00);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .actions button {
            background: var(--accent-color, #eccb00);
            color: #000;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
        }
        .actions button:hover {
            opacity: 0.9;
        }
        .channel-selector {
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
        }
        .channel-selector select {
            padding: 0.5rem;
            border-radius: 4px;
            background: #2a2a2a;
            color: #fff;
            border: 1px solid #444;
        }
        .item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color, #333);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
        }
        .item.read {
            opacity: 0.6;
        }
        .item-author {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            gap: 0.75rem;
        }
        .item-author img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }
        .item-author .name {
            font-weight: bold;
        }
        .item-author .date {
            font-size: 0.8rem;
            color: #888;
        }
        .item-content {
            line-height: 1.6;
        }
        .item-content img {
            max-width: 100%;
            border-radius: 4px;
        }
        .item-actions {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
        }
        .item-actions a, .item-actions button {
            background: none;
            border: none;
            color: var(--accent-color, #eccb00);
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
        }
        .item-actions button:hover, .item-actions a:hover {
            text-decoration: underline;
        }
        .login-prompt {
            text-align: center;
            padding: 4rem 0;
        }
        .login-prompt input {
            padding: 0.5rem;
            width: 300px;
            margin-bottom: 1rem;
            background: #2a2a2a;
            color: #fff;
            border: 1px solid #444;
            border-radius: 4px;
        }
        #error-msg {
            color: #ff4444;
            margin-top: 1rem;
        }
    </style>
</head>
<body>

    <div id="login-view" class="login-prompt" style="display: none;">
        <h1>Login to Reader</h1>
        <p>Authenticate using your token to read your feeds.</p>
        <input type="text" id="token-input" placeholder="Bearer Token">
        <br>
        <button onclick="login()" style="background: var(--accent-color, #eccb00); color: #000; border: none; padding: 0.5rem 1rem; border-radius: 4px; font-weight: bold; cursor: pointer;">Connect</button>
        <div id="error-msg"></div>
    </div>

    <div id="reader-view" style="display: none;">
        <div class="header">
            <h1>Reader</h1>
            <div class="actions">
                <button onclick="fetchFeeds()">Fetch New Posts</button>
                <button onclick="logout()" style="background: transparent; color: #888; border: 1px solid #888; margin-left: 1rem;">Logout</button>
            </div>
        </div>

        <div class="channel-selector">
            <select id="channel-select" onchange="loadTimeline()">
                <option value="inbox">Inbox</option>
                <option value="notifications">Notifications</option>
            </select>
        </div>

        <div id="timeline">
            <p>Loading...</p>
        </div>
    </div>

    <script>
        const ENDPOINT = "{$endpoint}";
        let token = localStorage.getItem('microsub_token');

        window.onload = () => {
            if (token) {
                document.getElementById('reader-view').style.display = 'block';
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
                document.getElementById('reader-view').style.display = 'block';
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
                const select = document.getElementById('channel-select');
                select.innerHTML = '';
                data.channels.forEach(ch => {
                    const opt = document.createElement('option');
                    opt.value = ch.uid;
                    opt.textContent = ch.name;
                    select.appendChild(opt);
                });
                loadTimeline();
            } catch (err) {
                document.getElementById('error-msg').textContent = err.message;
            }
        }

        async function loadTimeline() {
            const channel = document.getElementById('channel-select').value;
            const container = document.getElementById('timeline');
            container.innerHTML = '<p>Loading...</p>';

            try {
                const data = await api('timeline', 'GET', { channel });
                container.innerHTML = '';
                
                if (data.items.length === 0) {
                    container.innerHTML = '<p>No items found.</p>';
                    return;
                }

                data.items.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'item' + (item._is_read ? ' read' : '');
                    
                    let authorHtml = '';
                    if (item.author) {
                        authorHtml = `
                            <div class="item-author">
                                \${item.author.photo ? `<img src="\${item.author.photo}">` : ''}
                                <span class="name">\${item.author.name}</span>
                                <span class="date">\${new Date(item.published).toLocaleString()}</span>
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
                container.innerHTML = '<p style="color:red">Failed to load timeline.</p>';
            }
        }

        async function markRead(id) {
            const channel = document.getElementById('channel-select').value;
            try {
                await api('timeline', 'POST', { method: 'mark_read', channel, entry: id });
                loadTimeline();
            } catch (err) {
                alert("Failed to mark as read");
            }
        }

        async function fetchFeeds() {
            alert("Background fetch triggered. This will pull new items from feeds.");
            try {
                await api('fetch', 'POST');
                setTimeout(loadTimeline, 1000);
            } catch (err) {
                alert("Failed to trigger fetch.");
                console.error(err);
            }
        }
    </script>
</body>
</html>
HTML;
    }
}
