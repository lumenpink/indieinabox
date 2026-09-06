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
            --bg-gradient: linear-gradient(135deg, #090d16 0%, #111827 50%, #1e1b4b 100%);
            --sidebar-bg: rgba(17, 24, 39, 0.9);
            --content-bg: #f8fafc;
            --accent: #eccb00;
            --text-light: #f3f4f6;
            --text-dark: #1f2937;
            --border-color: #374151;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
            display: flex;
            min-height: 100vh;
            background: var(--bg-gradient);
            color: var(--text-dark);
        }
        .admin-sidebar {
            width: 250px;
            background: var(--sidebar-bg);
            color: var(--text-light);
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border-color);
        }
        .admin-sidebar h1 {
            padding: 1.5rem;
            margin: 0;
            font-size: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--accent);
        }
        .admin-nav {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: 1rem 0;
        }
        .admin-nav a {
            color: var(--text-light);
            text-decoration: none;
            padding: 1rem 1.5rem;
            display: block;
            transition: background 0.2s, color 0.2s;
        }
        .admin-nav a:hover, .admin-nav a.active {
            background: rgba(255, 255, 255, 0.1);
            color: var(--accent);
            border-left: 4px solid var(--accent);
            padding-left: calc(1.5rem - 4px);
        }
        .admin-content {
            flex: 1;
            background: var(--content-bg);
            overflow-y: auto;
            position: relative;
        }
        /* Override child styles to prevent them from breaking the layout */
        .admin-content > iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body>
    <div class="admin-sidebar">
        <h1>Indieinabox</h1>
        <nav class="admin-nav">
            <?php 
                $channels = [];
                $currentChannel = 'inbox';
                if (($activeTab ?? '') === 'microsub') {
                    $db = \Indieinabox\Database::getDb();
                    $stmt = $db->query('SELECT uid, name FROM microsub_channels');
                    if ($stmt) {
                        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    $currentChannel = $_GET['channel'] ?? 'inbox';
                }
            ?>
            <a href="/admin/microsub" class="<?= ($activeTab ?? '') === 'microsub' ? 'active' : '' ?>">Timeline</a>
            <?php if (($activeTab ?? '') === 'microsub'): ?>
                <div class="channels-accordion" style="background: rgba(0,0,0,0.2); margin-bottom: 0.5rem; padding: 0.5rem 0;">
                    <?php foreach ($channels as $ch): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-right: 1rem;">
                            <a href="/admin/microsub?channel=<?= urlencode($ch['uid']) ?>" style="flex: 1; padding: 0.5rem 1.5rem 0.5rem 2.5rem; font-size: 0.9em; <?= $currentChannel === $ch['uid'] ? 'color: var(--accent); border-left: 2px solid var(--accent); padding-left: calc(2.5rem - 2px);' : 'border-left: 2px solid transparent; padding-left: calc(2.5rem - 2px);' ?>">
                                <?= htmlspecialchars($ch['name']) ?>
                            </a>
                            <?php if ($ch['uid'] !== 'inbox' && $ch['uid'] !== 'notifications'): ?>
                                <button onclick="deleteChannel('<?= htmlspecialchars($ch['uid']) ?>', '<?= htmlspecialchars(addslashes($ch['name'])) ?>')" style="background: none; border: none; color: #ff6b6b; cursor: pointer; padding: 0 0.5rem; font-size: 1.1em;" title="Remover canal">&times;</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <a href="#" onclick="createChannel(event)" style="padding: 0.5rem 1.5rem 0.5rem 2.5rem; font-size: 0.9em; color: #a770ef; border-left: 2px solid transparent; padding-left: calc(2.5rem - 2px);">
                        + Novo Canal
                    </a>
                </div>
                <script>
                    async function deleteChannel(uid, name) {
                        if (!confirm('Tem certeza que deseja remover o canal "' + name + '"?')) return;
                        try {
                            const res = await fetch('/microsub', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({ action: 'channels', method: 'delete', uid: uid })
                            });
                            if (res.ok) {
                                window.location.href = '/admin/microsub';
                            } else {
                                alert("Erro ao remover canal.");
                            }
                        } catch (err) {
                            console.error(err);
                            alert("Erro ao remover canal.");
                        }
                    }
                    async function createChannel(e) {
                        e.preventDefault();
                        const name = prompt("Nome do novo canal:");
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
                                alert("Erro ao criar canal.");
                            }
                        } catch (err) {
                            console.error(err);
                            alert("Erro ao criar canal.");
                        }
                    }
                </script>
            <?php endif; ?>
            <a href="/admin/moderation" class="<?= ($activeTab ?? '') === 'moderation' ? 'active' : '' ?>">Moderation</a>
            <a href="/admin/micropub" class="<?= ($activeTab ?? '') === 'micropub' ? 'active' : '' ?>">Publisher</a>
            <a href="/admin/config" class="<?= ($activeTab ?? '') === 'config' ? 'active' : '' ?>">Configuration</a>
            <a href="/admin/config?action=logout" style="margin-top: auto; border-top: 1px solid var(--border-color);">Logout</a>
        </nav>
    </div>
    <div class="admin-content">
        <?php if (isset($content)) echo $content; ?>
    </div>
</body>
</html>
