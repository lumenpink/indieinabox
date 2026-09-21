<?php

declare(strict_types=1);

namespace Indieinabox\Views\Admin;

use Indieinabox\Core\Database;
use PDO;

/**
 * Presentation view component that renders the administrative shell layout and navigation sidebar.
 */
class AdminLayoutView
{
    /**
     * Renders the administrative dashboard layout wrapping the inner view content.
     *
     * @param string $content HTML content to render inside the main viewport.
     * @param string $activeTab Currently active navigation tab (e.g. 'microsub', 'moderation', 'micropub', 'config').
     * @param string $fqdn Fully qualified domain name of the site.
     * @return string Rendered HTML layout.
     */
    public static function render(string $content, string $activeTab = 'microsub', string $fqdn = ''): string
    {
        $channels = [];
        $currentChannel = 'inbox';

        if ($activeTab === 'microsub') {
            try {
                $db = Database::getDb();
                $stmt = $db->query('SELECT uid, name FROM microsub_channels');
                if ($stmt) {
                    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (\Throwable) {
                $channels = [];
            }
            $currentChannel = (string) ($_GET['channel'] ?? 'inbox');
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indieinabox Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* 90's Anime Cyberpunk Neon (Ghost in the Shell / Akira / Memories) */
            --cyber-bg: #090b10;
            --cyber-surface: #0f141f;
            --cyber-surface-2: #141c2b;
            --cyber-border: rgba(0, 240, 255, 0.22);
            --cyber-border-pink: rgba(255, 42, 133, 0.35);
            --neon-pink: #ff2a85;
            --neon-pink-glow: rgba(255, 42, 133, 0.4);
            --neon-cyan: #00f0ff;
            --neon-cyan-glow: rgba(0, 240, 255, 0.35);
            --neon-white: #ffffff;
            --neon-amber: #ffd000;
            --neon-green: #00ff9f;
            --text-light: #f8fafc;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
            --accent: var(--neon-pink);
            --border-color: var(--cyber-border);
            --bg-gradient: radial-gradient(circle at 12% 15%, rgba(255, 42, 133, 0.09) 0%, transparent 45%),
                           radial-gradient(circle at 88% 85%, rgba(0, 240, 255, 0.08) 0%, transparent 50%),
                           linear-gradient(160deg, #080a0f 0%, #0d121c 55%, #121826 100%);
            --sidebar-bg: rgba(9, 13, 20, 0.96);
            --content-bg: transparent;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
            display: flex;
            min-height: 100vh;
            background: var(--bg-gradient);
            color: var(--text-light);
            overflow-x: hidden;
        }
        /* Custom Neon Scrollbars */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #090c13;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(0, 240, 255, 0.3);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--neon-pink);
            box-shadow: 0 0 10px var(--neon-pink);
        }
        .admin-sidebar {
            width: 270px;
            background: var(--sidebar-bg);
            color: var(--text-light);
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--cyber-border);
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 20;
        }
        .admin-sidebar::after {
            content: '';
            position: absolute;
            top: 0;
            right: -1px;
            width: 1px;
            height: 100%;
            background: linear-gradient(180deg, var(--neon-pink) 0%, var(--neon-cyan) 50%, transparent 100%);
            opacity: 0.6;
            pointer-events: none;
        }
        .sidebar-header {
            padding: 1.5rem 1.25rem 1.25rem;
            border-bottom: 1px solid var(--cyber-border);
            background: linear-gradient(180deg, rgba(255, 42, 133, 0.08) 0%, transparent 100%);
        }
        .sidebar-header h1 {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--neon-white);
            text-shadow: 0 0 12px var(--neon-pink-glow);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sidebar-header h1 .logo-dot {
            width: 8px;
            height: 8px;
            background: var(--neon-pink);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--neon-pink);
            display: inline-block;
        }
        .sidebar-header .hud-badge {
            display: inline-block;
            margin-top: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.68rem;
            letter-spacing: 0.12em;
            color: var(--neon-cyan);
            border: 1px solid rgba(0, 240, 255, 0.3);
            background: rgba(0, 240, 255, 0.08);
            padding: 2px 7px;
            border-radius: 3px;
        }
        .admin-nav {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: 1rem 0;
        }
        .nav-section-title {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.65rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--text-dim);
            padding: 0.75rem 1.5rem 0.35rem;
        }
        .admin-nav a {
            color: var(--text-muted);
            text-decoration: none;
            padding: 0.85rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            font-weight: 500;
            letter-spacing: 0.02em;
            transition: all 0.2s ease-in-out;
            border-left: 3px solid transparent;
            position: relative;
        }
        .admin-nav a .nav-index {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.72rem;
            color: var(--text-dim);
            transition: color 0.2s;
        }
        .admin-nav a:hover {
            color: var(--neon-cyan);
            background: rgba(0, 240, 255, 0.06);
            border-left-color: var(--neon-cyan);
        }
        .admin-nav a:hover .nav-index {
            color: var(--neon-cyan);
        }
        .admin-nav a.active {
            color: var(--neon-white);
            background: linear-gradient(90deg, rgba(255, 42, 133, 0.16) 0%, transparent 100%);
            border-left: 3px solid var(--neon-pink);
            box-shadow: inset 3px 0 12px var(--neon-pink-glow);
            font-weight: 600;
        }
        .admin-nav a.active .nav-index {
            color: var(--neon-pink);
            text-shadow: 0 0 8px var(--neon-pink-glow);
        }
        .channels-accordion {
            background: rgba(0, 0, 0, 0.35);
            margin: 0.25rem 0 0.75rem;
            padding: 0.4rem 0;
            border-top: 1px solid rgba(0, 240, 255, 0.1);
            border-bottom: 1px solid rgba(0, 240, 255, 0.1);
        }
        .admin-nav a.logout-link {
            margin-top: auto;
            border-top: 1px solid var(--cyber-border);
            color: #f43f5e;
        }
        .admin-nav a.logout-link:hover {
            background: rgba(244, 63, 94, 0.1);
            border-left-color: #f43f5e;
            color: #fda4af;
        }
        .admin-content {
            flex: 1;
            background: var(--content-bg);
            overflow-y: auto;
            position: relative;
            min-height: 100vh;
        }
        .admin-content > iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body>
    <div class="admin-sidebar">
        <div class="sidebar-header">
            <h1><span class="logo-dot"></span>Indieinabox</h1>
            <span class="hud-badge">SYS // CYBER-DECK</span>
        </div>
        <nav class="admin-nav">
            <span class="nav-section-title">CONTROL MODULES</span>
            <a href="/admin/microsub" class="<?= $activeTab === 'microsub' ? 'active' : '' ?>">
                <span class="nav-index">01</span> Timeline
            </a>
            <?php if ($activeTab === 'microsub'): ?>
                <div class="channels-accordion">
                    <?php foreach ($channels as $ch): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-right: 1rem;">
                            <a href="/admin/microsub?channel=<?= urlencode($ch['uid']) ?>" style="flex: 1; padding: 0.45rem 1.5rem 0.45rem 2.5rem; font-size: 0.86rem; <?= $currentChannel === $ch['uid'] ? 'color: var(--neon-cyan); border-left: 2px solid var(--neon-cyan); padding-left: calc(2.5rem - 2px); font-weight: 600;' : 'border-left: 2px solid transparent; padding-left: calc(2.5rem - 2px);' ?>">
                                # <?= htmlspecialchars($ch['name']) ?>
                            </a>
                            <?php if ($ch['uid'] !== 'inbox' && $ch['uid'] !== 'notifications'): ?>
                                <button onclick="deleteChannel('<?= htmlspecialchars($ch['uid']) ?>', '<?= htmlspecialchars(addslashes($ch['name'])) ?>')" style="background: none; border: none; color: #f43f5e; cursor: pointer; padding: 0 0.5rem; font-size: 1.1em;" title="Delete channel">&times;</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <a href="#" onclick="createChannel(event)" style="padding: 0.45rem 1.5rem 0.45rem 2.5rem; font-size: 0.86rem; color: var(--neon-pink); border-left: 2px solid transparent; padding-left: calc(2.5rem - 2px); font-family: 'JetBrains Mono', monospace;">
                        + NEW CHANNEL
                    </a>
                </div>
                <script>
                    async function deleteChannel(uid, name) {
                        if (!confirm('Are you sure you want to delete channel "' + name + '"?')) return;
                        try {
                            const res = await fetch('/microsub', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({ action: 'channels', method: 'delete', uid: uid })
                            });
                            if (res.ok) {
                                window.location.href = '/admin/microsub';
                            } else {
                                alert("Failed to delete channel.");
                            }
                        } catch (err) {
                            console.error(err);
                            alert("Failed to delete channel.");
                        }
                    }
                    async function createChannel(e) {
                        e.preventDefault();
                        const name = prompt("Name of the new channel:");
                        if (!name) return;
                        try {
                            const res = await fetch('/microsub', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({ action: 'channels', method: 'create', name: name })
                            });
                            if (res.ok) {
                                const data = await res.json();
                                window.location.href = '/admin/microsub?channel=' + data.uid;
                            } else {
                                alert("Failed to create channel.");
                            }
                        } catch (err) {
                            console.error(err);
                            alert("Failed to create channel.");
                        }
                    }
                </script>
            <?php endif; ?>
            <a href="/admin/moderation" class="<?= $activeTab === 'moderation' ? 'active' : '' ?>">
                <span class="nav-index">02</span> Moderation
            </a>
            <a href="/admin/micropub" class="<?= $activeTab === 'micropub' ? 'active' : '' ?>">
                <span class="nav-index">03</span> Publisher
            </a>
            <a href="/admin/config" class="<?= $activeTab === 'config' ? 'active' : '' ?>">
                <span class="nav-index">04</span> Configuration
            </a>
            <a href="/admin/config?action=logout" class="logout-link">
                <span class="nav-index">05</span> Logout
            </a>
        </nav>
    </div>
    <div class="admin-content">
        <?= $content ?>
    </div>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }
}
