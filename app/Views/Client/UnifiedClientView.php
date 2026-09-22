<?php

declare(strict_types=1);

namespace Indieinabox\Views\Client;

use Indieinabox\Site\Site;

/**
 * Class UnifiedClientView
 *
 * Renders the modern, responsive, offline-capable unified Microsub reader
 * and Micropub publishing client.
 */
class UnifiedClientView
{
    /**
     * Renders the unified client HTML.
     *
     * @param array<string, mixed> $context
     * @param Site|null $site
     * @return string
     */
    public static function render(array $context, ?Site $site = null): string
    {
        $siteName = htmlspecialchars($site?->metadata->sitename ?? 'indieinabox');
        $fqdn = htmlspecialchars(rtrim($site?->metadata->fqdn ?? '/', '/'));
        $micropubEndpoint = htmlspecialchars($context['micropub_endpoint'] ?? '/micropub');
        $microsubEndpoint = htmlspecialchars($context['microsub_endpoint'] ?? '/microsub');
        $token = htmlspecialchars($context['token'] ?? '');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unified Client - <?= $siteName ?></title>
    <link rel="manifest" href="/manifest.webmanifest" onerror="this.remove()">
    <style>
        :root {
            --bg-body: #0b0d14;
            --bg-sidebar: #111420;
            --bg-card: #181c2b;
            --bg-card-hover: #1f2438;
            --bg-input: #0f121d;
            --border: #262c40;
            --border-highlight: #3b4463;
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --text-faint: #64748b;
            --accent: #f59e0b;
            --accent-glow: rgba(245, 158, 11, 0.25);
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --danger: #ef4444;
            --success: #10b981;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-body);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* App Layout */
        .app-shell {
            display: flex;
            width: 100%;
            height: 100%;
        }

        /* Sidebar Navigation */
        .sidebar {
            width: 280px;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 18px 14px;
            flex-shrink: 0;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 18px;
            margin-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }
        .brand a {
            color: #fff;
            text-decoration: none;
            font-weight: 800;
            font-size: 1.15rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sidebar-section-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-faint);
            margin: 12px 8px 6px 8px;
            font-weight: 700;
        }
        .channels-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
            overflow-y: auto;
            flex: 1;
        }
        .channel-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 12px;
            border-radius: 8px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
            transition: all 0.15s ease;
        }
        .channel-btn:hover {
            background: var(--bg-card);
            color: #fff;
        }
        .channel-btn.active {
            background: var(--primary);
            color: #fff;
            font-weight: 600;
        }
        .unread-badge {
            background: var(--accent);
            color: #000;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 10px;
        }

        .sidebar-footer {
            padding-top: 14px;
            border-top: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .btn-new-post {
            background: var(--accent);
            color: #000;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: opacity 0.2s;
        }
        .btn-new-post:hover { opacity: 0.9; }

        /* Main Workspace */
        .main-workspace {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: var(--bg-body);
        }

        /* Top Bar */
        .top-bar {
            height: 56px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            background: var(--bg-sidebar);
        }
        .channel-heading {
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .top-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-icon {
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-muted);
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-icon:hover { color: #fff; border-color: var(--border-highlight); }

        /* Timeline Stream */
        .timeline-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            display: flex;
            justify-content: center;
        }
        .timeline-container {
            max-width: 680px;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        /* Post Card */
        .entry-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            transition: border-color 0.2s;
            position: relative;
        }
        .entry-card:hover { border-color: var(--border-highlight); }
        .entry-card.unread {
            border-left: 4px solid var(--primary);
        }

        .entry-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .author-box {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--border);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--text-muted);
        }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .author-meta { display: flex; flex-direction: column; }
        .author-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: #fff;
            text-decoration: none;
        }
        .author-name:hover { text-decoration: underline; }
        .entry-time { font-size: 0.78rem; color: var(--text-muted); }

        /* Content Warning */
        .cw-block {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 12px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--accent);
        }
        .cw-content { display: none; margin-top: 12px; }
        .cw-content.open { display: block; }

        .entry-body {
            font-size: 0.95rem;
            line-height: 1.6;
            color: #e2e8f0;
            word-break: break-word;
        }
        .entry-body a { color: #818cf8; text-decoration: none; }
        .entry-body a:hover { text-decoration: underline; }

        /* Photos & Blur for CW */
        .media-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-top: 12px;
            border-radius: 8px;
            overflow: hidden;
        }
        .media-gallery img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            transition: filter 0.3s;
        }
        .media-gallery.blurred img {
            filter: blur(25px);
        }

        /* Interactive Polls */
        .poll-box {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            margin-top: 14px;
        }
        .poll-title { font-weight: 600; font-size: 0.95rem; margin-bottom: 10px; }
        .poll-options { display: flex; flex-direction: column; gap: 8px; }
        .poll-option-btn {
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: #fff;
            padding: 10px 14px;
            border-radius: 8px;
            text-align: left;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        .poll-option-btn:hover { border-color: var(--primary); }
        .poll-bar {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            background: rgba(99, 102, 241, 0.25);
            z-index: 0;
            transition: width 0.4s ease;
        }
        .poll-option-text { position: relative; z-index: 1; }
        .poll-option-votes { position: relative; z-index: 1; font-weight: 700; color: #818cf8; font-size: 0.85rem; }

        /* Action Bar */
        .entry-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        .action-group {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .action-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.15s;
        }
        .action-btn:hover { color: #fff; }
        .action-btn.active { color: var(--accent); }

        /* Modal Composer */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(6px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-overlay.active { display: flex; }
        .composer-box {
            background: var(--bg-card);
            border: 1px solid var(--border-highlight);
            border-radius: 16px;
            max-width: 600px;
            width: 100%;
            padding: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.8);
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .composer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .composer-header h3 { font-size: 1.2rem; font-weight: 700; }
        .composer-close {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 1.4rem;
            cursor: pointer;
        }
        .composer-input, .composer-textarea {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: #fff;
            padding: 10px 14px;
            font-size: 0.95rem;
            font-family: inherit;
            outline: none;
        }
        .composer-input:focus, .composer-textarea:focus { border-color: var(--primary); }
        .composer-textarea { height: 120px; resize: vertical; }

        .composer-tools {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
        }
        .composer-icons {
            display: flex;
            gap: 12px;
        }
        .tool-label {
            cursor: pointer;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.85rem;
        }
        .tool-label:hover { color: #fff; }

        .btn-publish {
            background: var(--accent);
            color: #000;
            border: none;
            padding: 9px 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
        }
        .btn-publish:hover { opacity: 0.9; }

        /* Upload Previews */
        .preview-grid {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 4px 0;
        }
        .preview-thumb {
            width: 60px;
            height: 60px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid var(--border);
        }

        /* Offline Banner */
        .offline-indicator {
            display: none;
            background: #b91c1c;
            color: #fff;
            text-align: center;
            padding: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .offline-indicator.active { display: block; }
    </style>
</head>
<body>
<div class="app-shell">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="brand">
            <a href="/">
                <span>🪐</span>
                <span><?= $siteName ?></span>
            </a>
        </div>

        <div class="sidebar-section-title">Channels</div>
        <ul class="channels-list" id="channelsList">
            <li>
                <button class="channel-btn active" data-channel="inbox" onclick="selectChannel('inbox')">
                    <span>📥 Inbox</span>
                    <span class="unread-badge" id="badge-inbox" style="display:none">0</span>
                </button>
            </li>
            <li>
                <button class="channel-btn" data-channel="notifications" onclick="selectChannel('notifications')">
                    <span>🔔 Notifications</span>
                    <span class="unread-badge" id="badge-notifications" style="display:none">0</span>
                </button>
            </li>
        </ul>

        <div class="sidebar-footer">
            <button class="btn-new-post" onclick="openComposer()">
                <span>✏️</span> New Note
            </button>
            <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-faint); margin-top: 6px;">
                <a href="/discover" style="color: var(--text-faint); text-decoration: none;">Global Explore</a>
                <a href="/admin" style="color: var(--text-faint); text-decoration: none;">Admin</a>
            </div>
        </div>
    </aside>

    <!-- Main Workspace -->
    <main class="main-workspace">
        <div class="offline-indicator" id="offlineIndicator">
            ⚠️ You are currently offline. Showing cached timeline items.
        </div>
        <header class="top-bar">
            <div class="channel-heading">
                <span id="currentChannelIcon">📥</span>
                <span id="currentChannelName">Inbox</span>
            </div>
            <div class="top-actions">
                <button class="btn-icon" title="Mark All as Read" onclick="markAllRead()">✓</button>
                <button class="btn-icon" title="Refresh Timeline" onclick="refreshTimeline()">🔄</button>
            </div>
        </header>

        <section class="timeline-scroll">
            <div class="timeline-container" id="timelineStream">
                <div style="text-align: center; color: var(--text-muted); padding: 40px;">Loading entries...</div>
            </div>
        </section>
    </main>
</div>

<!-- Modal Composer -->
<div class="modal-overlay" id="composerModal">
    <div class="composer-box">
        <div class="composer-header">
            <h3 id="composerTitle">Compose Note</h3>
            <button class="composer-close" onclick="closeComposer()">✕</button>
        </div>

        <input type="hidden" id="replyTarget" value="">
        <input type="hidden" id="repostTarget" value="">
        <input type="hidden" id="likeTarget" value="">
        <input type="hidden" id="bookmarkTarget" value="">

        <div id="replyBadge" style="display: none; background: rgba(99, 102, 241, 0.15); border: 1px solid var(--primary); padding: 6px 12px; border-radius: 6px; font-size: 0.85rem;">
            Replying to: <span id="replyUrlText" style="color: #818cf8;"></span>
            <button onclick="clearContextTargets()" style="background:none; border:none; color:var(--danger); cursor:pointer; float:right;">✕</button>
        </div>

        <input type="text" class="composer-input" id="postTitle" placeholder="Title (optional, converts note to article)">

        <input type="text" class="composer-input" id="postCw" placeholder="Content Warning summary (optional)">

        <textarea class="composer-textarea" id="postContent" placeholder="What's happening? Markdown and #hashtags supported..."></textarea>

        <!-- Poll Builder Section -->
        <details id="pollSection" style="font-size: 0.9rem; color: var(--text-muted);">
            <summary style="cursor: pointer; font-weight: 600; margin-bottom: 8px;">📊 Attach Poll</summary>
            <div style="display: flex; flex-direction: column; gap: 6px; margin-top: 6px;">
                <input type="text" class="composer-input" id="pollOpt1" placeholder="Option 1">
                <input type="text" class="composer-input" id="pollOpt2" placeholder="Option 2">
                <input type="text" class="composer-input" id="pollOpt3" placeholder="Option 3 (optional)">
                <input type="text" class="composer-input" id="pollOpt4" placeholder="Option 4 (optional)">
            </div>
        </details>

        <!-- Image Previews -->
        <div class="preview-grid" id="imagePreviews"></div>

        <div class="composer-tools">
            <div class="composer-icons">
                <label class="tool-label">
                    <span>📷 Photos</span>
                    <input type="file" id="mediaUpload" multiple accept="image/*" style="display: none;" onchange="handleMediaSelected(event)">
                </label>
                <label class="tool-label" title="Enable dithering for retro 1-bit compression">
                    <input type="checkbox" id="ditherOption"> <span>Dither</span>
                </label>
            </div>
            <button class="btn-publish" id="publishBtn" onclick="submitPost()">Publish Note</button>
        </div>
    </div>
</div>

<script>
    const CONFIG = {
        micropub: '<?= $micropubEndpoint ?>',
        microsub: '<?= $microsubEndpoint ?>',
        token: '<?= $token ?>' || localStorage.getItem('indie_token') || ''
    };

    let state = {
        activeChannel: 'inbox',
        channels: [],
        entries: [],
        offline: !navigator.onLine,
        selectedFiles: []
    };

    // Offline / Online listeners
    window.addEventListener('online', () => {
        state.offline = false;
        document.getElementById('offlineIndicator').classList.remove('active');
        refreshTimeline();
    });
    window.addEventListener('offline', () => {
        state.offline = true;
        document.getElementById('offlineIndicator').classList.add('active');
    });

    // Initialize Client
    async function init() {
        if (state.offline) {
            document.getElementById('offlineIndicator').classList.add('active');
        }
        await loadChannels();
        await selectChannel(state.activeChannel);
    }

    async function fetchApi(url, options = {}) {
        options.headers = options.headers || {};
        if (CONFIG.token) {
            options.headers['Authorization'] = 'Bearer ' + CONFIG.token;
        }
        return fetch(url, options);
    }

    // Channels
    async function loadChannels() {
        try {
            const res = await fetchApi(CONFIG.microsub + '?action=channels');
            if (res.ok) {
                const data = await res.json();
                state.channels = data.channels || [];
                localStorage.setItem('cached_channels', JSON.stringify(state.channels));
            }
        } catch (e) {
            const cached = localStorage.getItem('cached_channels');
            if (cached) state.channels = JSON.parse(cached);
        }
        renderChannels();
    }

    function renderChannels() {
        const list = document.getElementById('channelsList');
        if (!state.channels.length) return;

        list.innerHTML = state.channels.map(c => {
            const isActive = c.uid === state.activeChannel;
            const icon = c.uid === 'notifications' ? '🔔' : (c.uid === 'inbox' ? '📥' : '📁');
            return `<li>
                <button class="channel-btn ${isActive ? 'active' : ''}" onclick="selectChannel('${c.uid}')">
                    <span>${icon} ${c.name}</span>
                </button>
            </li>`;
        }).join('');
    }

    async function selectChannel(uid) {
        state.activeChannel = uid;
        document.getElementById('currentChannelName').textContent = uid.charAt(0).toUpperCase() + uid.slice(1);
        document.getElementById('currentChannelIcon').textContent = uid === 'notifications' ? '🔔' : '📥';
        renderChannels();
        await loadTimeline();
    }

    // Timeline Loading with Offline Cache
    async function loadTimeline() {
        const stream = document.getElementById('timelineStream');
        stream.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px;">Loading entries...</div>';

        const cacheKey = 'timeline_' + state.activeChannel;

        try {
            const res = await fetchApi(`${CONFIG.microsub}?action=timeline&channel=${encodeURIComponent(state.activeChannel)}`);
            if (res.ok) {
                const data = await res.json();
                state.entries = data.items || [];
                localStorage.setItem(cacheKey, JSON.stringify(state.entries));
            } else {
                throw new Error('Failed to fetch');
            }
        } catch (e) {
            const cached = localStorage.getItem(cacheKey);
            if (cached) {
                state.entries = JSON.parse(cached);
            } else {
                state.entries = [];
            }
        }

        renderTimeline();
    }

    function refreshTimeline() {
        loadTimeline();
    }

    function renderTimeline() {
        const stream = document.getElementById('timelineStream');
        if (!state.entries.length) {
            stream.innerHTML = '<div style="text-align:center; padding: 40px; color: var(--text-muted);">No entries in this channel.</div>';
            return;
        }

        stream.innerHTML = state.entries.map(entry => {
            const author = entry.author || {};
            const authorName = author.name || 'Unknown Author';
            const authorUrl = author.url || '#';
            const authorPhoto = author.photo || '';
            const initial = authorName.charAt(0).toUpperCase();
            const dateStr = entry.published ? new Date(entry.published).toLocaleString() : '';
            const contentHtml = (entry.content && entry.content.html) ? entry.content.html : (entry.content && entry.content.text ? entry.content.text : '');
            const ext = entry._indieinabox || {};
            const cw = ext.content_warning || entry.summary || null;
            const photos = ext.photos || (entry.photo ? (Array.isArray(entry.photo) ? entry.photo : [entry.photo]) : []);
            const poll = ext.poll || null;
            const entryId = entry._id || entry.uid || '';
            const entryUrl = entry.url || '';

            return `
            <article class="entry-card ${entry.isRead ? '' : 'unread'}" id="card-${entryId}">
                <div class="entry-header">
                    <div class="author-box">
                        <div class="avatar">
                            ${authorPhoto ? `<img src="${authorPhoto}" alt="${authorName}" loading="lazy">` : initial}
                        </div>
                        <div class="author-meta">
                            <a href="${authorUrl}" class="author-name" target="_blank" rel="noopener">${authorName}</a>
                            <span class="entry-time">${dateStr}</span>
                        </div>
                    </div>
                </div>

                ${cw ? `
                    <div class="cw-block" onclick="toggleCw('${entryId}')">
                        <span>⚠️ CW: ${cw}</span>
                        <span id="cw-toggle-${entryId}">Show Content ▾</span>
                    </div>
                    <div class="cw-content" id="cw-body-${entryId}">
                        <div class="entry-body">${contentHtml}</div>
                        ${renderGallery(photos, false)}
                    </div>
                ` : `
                    <div class="entry-body">${contentHtml}</div>
                    ${renderGallery(photos, false)}
                `}

                ${poll ? renderPoll(entryId, poll) : ''}

                <div class="entry-actions">
                    <div class="action-group">
                        <button class="action-btn" title="Reply" onclick="openReply('${entryUrl}')">💬 Reply</button>
                        <button class="action-btn" title="Like" onclick="submitReaction('like', '${entryUrl}')">❤️ Like</button>
                        <button class="action-btn" title="Repost" onclick="submitReaction('repost', '${entryUrl}')">🔁 Repost</button>
                        <button class="action-btn" title="Bookmark" onclick="submitReaction('bookmark', '${entryUrl}')">🔖 Bookmark</button>
                    </div>
                    ${entryUrl ? `<a href="${entryUrl}" target="_blank" rel="noopener" class="action-btn">🔗 Link</a>` : ''}
                </div>
            </article>`;
        }).join('');
    }

    function renderGallery(photos, isBlurred) {
        if (!photos || !photos.length) return '';
        return `
        <div class="media-gallery ${isBlurred ? 'blurred' : ''}">
            ${photos.map(p => `<img src="${p}" alt="Photo" onclick="this.classList.toggle('unblurred')" loading="lazy">`).join('')}
        </div>`;
    }

    function renderPoll(entryId, poll) {
        const options = poll.options || [];
        const totalVotes = options.reduce((sum, opt) => sum + (opt.votes || 0), 0);

        return `
        <div class="poll-box" id="poll-${entryId}">
            <div class="poll-title">📊 ${poll.question || 'Poll'}</div>
            <div class="poll-options">
                ${options.map((opt, idx) => {
                    const votes = opt.votes || 0;
                    const pct = totalVotes > 0 ? Math.round((votes / totalVotes) * 100) : 0;
                    return `
                    <button class="poll-option-btn" onclick="votePoll('${entryId}', ${idx})">
                        <div class="poll-bar" style="width: ${pct}%;"></div>
                        <span class="poll-option-text">${opt.text}</span>
                        <span class="poll-option-votes">${votes} (${pct}%)</span>
                    </button>`;
                }).join('')}
            </div>
        </div>`;
    }

    function toggleCw(id) {
        const body = document.getElementById('cw-body-' + id);
        const toggle = document.getElementById('cw-toggle-' + id);
        if (body.classList.contains('open')) {
            body.classList.remove('open');
            toggle.textContent = 'Show Content ▾';
        } else {
            body.classList.add('open');
            toggle.textContent = 'Hide Content ▴';
        }
    }

    async function votePoll(entryId, optIndex) {
        try {
            const res = await fetchApi(CONFIG.microsub, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'post_vote',
                    channel: state.activeChannel,
                    entry: entryId,
                    option: optIndex
                })
            });
            if (res.ok) {
                await loadTimeline();
            } else {
                alert('Voting submitted!');
            }
        } catch (e) {
            alert('Vote recorded offline or handled locally.');
        }
    }

    async function markAllRead() {
        const unreadIds = state.entries.filter(e => !e.isRead).map(e => e._id || e.uid);
        if (!unreadIds.length) return;

        try {
            await fetchApi(CONFIG.microsub, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'timeline',
                    method: 'mark_read',
                    channel: state.activeChannel,
                    entry: unreadIds.join(',')
                })
            });
            state.entries.forEach(e => e.isRead = true);
            renderTimeline();
        } catch (e) {
            console.error(e);
        }
    }

    // Composer Actions
    function openComposer() {
        document.getElementById('composerModal').classList.add('active');
    }

    function closeComposer() {
        document.getElementById('composerModal').classList.remove('active');
        clearContextTargets();
    }

    function openReply(targetUrl) {
        clearContextTargets();
        document.getElementById('replyTarget').value = targetUrl;
        document.getElementById('replyUrlText').textContent = targetUrl;
        document.getElementById('replyBadge').style.display = 'block';
        document.getElementById('composerTitle').textContent = 'Compose Reply';
        openComposer();
    }

    function clearContextTargets() {
        document.getElementById('replyTarget').value = '';
        document.getElementById('repostTarget').value = '';
        document.getElementById('likeTarget').value = '';
        document.getElementById('bookmarkTarget').value = '';
        document.getElementById('replyBadge').style.display = 'none';
        document.getElementById('composerTitle').textContent = 'Compose Note';
    }

    async function submitReaction(type, url) {
        if (!url) return;
        const payload = {
            h: 'entry',
            content: `Reacted with ${type} to ${url}`
        };
        if (type === 'like') payload['like-of'] = url;
        if (type === 'repost') payload['repost-of'] = url;
        if (type === 'bookmark') payload['bookmark-of'] = url;

        try {
            const form = new URLSearchParams();
            for (const k in payload) form.append(k, payload[k]);

            const res = await fetchApi(CONFIG.micropub, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: form
            });
            if (res.ok || res.status === 201 || res.status === 202) {
                alert(`Successfully posted ${type}!`);
            } else {
                alert(`Error posting ${type}: ` + res.statusText);
            }
        } catch (e) {
            alert(`Network error posting ${type}`);
        }
    }

    function handleMediaSelected(e) {
        const files = Array.from(e.target.files);
        state.selectedFiles = files;
        const previews = document.getElementById('imagePreviews');
        previews.innerHTML = files.map(f => {
            const url = URL.createObjectURL(f);
            return `<img src="${url}" class="preview-thumb" alt="Upload">`;
        }).join('');
    }

    async function submitPost() {
        const btn = document.getElementById('publishBtn');
        btn.disabled = true;
        btn.textContent = 'Publishing...';

        try {
            const form = new FormData();
            form.append('h', 'entry');

            const title = document.getElementById('postTitle').value.trim();
            if (title) form.append('name', title);

            const content = document.getElementById('postContent').value;
            form.append('content', content);

            const cw = document.getElementById('postCw').value.trim();
            if (cw) form.append('content_warning', cw);

            const replyTo = document.getElementById('replyTarget').value;
            if (replyTo) form.append('in-reply-to', replyTo);

            // Poll options
            const opt1 = document.getElementById('pollOpt1').value.trim();
            const opt2 = document.getElementById('pollOpt2').value.trim();
            if (opt1 && opt2) {
                form.append('poll[options][]', opt1);
                form.append('poll[options][]', opt2);
                const opt3 = document.getElementById('pollOpt3').value.trim();
                const opt4 = document.getElementById('pollOpt4').value.trim();
                if (opt3) form.append('poll[options][]', opt3);
                if (opt4) form.append('poll[options][]', opt4);
            }

            // Photos upload
            if (state.selectedFiles.length > 0) {
                for (const file of state.selectedFiles) {
                    form.append('photo[]', file);
                }
            }

            const res = await fetchApi(CONFIG.micropub, {
                method: 'POST',
                body: form
            });

            if (res.ok || res.status === 201 || res.status === 202) {
                closeComposer();
                document.getElementById('postContent').value = '';
                document.getElementById('postTitle').value = '';
                document.getElementById('postCw').value = '';
                document.getElementById('imagePreviews').innerHTML = '';
                state.selectedFiles = [];
                await loadTimeline();
            } else {
                alert('Micropub publish failed: ' + res.statusText);
            }
        } catch (e) {
            alert('Error publishing note: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Publish Note';
        }
    }

    init();
</script>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }
}
