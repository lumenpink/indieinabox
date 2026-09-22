<?php

declare(strict_types=1);

namespace Indieinabox\Views\Admin;

use Indieinabox\Core\Database;
use PDO;

/**
 * Presentation view component that renders the administrative shell layout and navigation sidebar.
 * Estética: Lambe-Lambe Periférico, Dissidência & Resistência DIY (Neo-Brutalismo Urbano).
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
    <title>Indieinabox Admin // Trincheira DIY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Bebas+Neue&family=Outfit:wght@400;600;800&family=JetBrains+Mono:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Estética Lambe-Lambe Periférico & Neo-Brutalismo DIY */
            --lambe-bg: #141518;            /* Asfalto / Muro Noturno */
            --lambe-paper: #f4efe6;         /* Papel Sulfite Cru */
            --lambe-surface: #1e1f25;       /* Camada de cartaz escuro */
            --lambe-surface-2: #282a32;
            --lambe-ink-black: #0a0a0c;     /* Tinta preta pura */
            --tint-magenta: #ff007f;        /* Rosa Choque / Travesti / Resistência */
            --tint-yellow: #ffe600;         /* Amarelo Alerta de Rua */
            --tint-cyan: #00e5ff;           /* Ciano Estêncil */
            --tint-red: #ff2244;
            --tape-cream: #f6ecbe;          /* Fita Crepe */
            --border-hard: 2px solid #000000;
            --border-hard-thick: 3px solid #000000;
            --stamp-shadow: 4px 4px 0px #000000;
            --stamp-shadow-sm: 2px 2px 0px #000000;
            --text-light: #f4efe6;
            --text-muted: #a0a4b0;
            --text-dim: #727684;
            --sidebar-bg: #141518;
        }

        * {
            box-sizing: border-box;
            border-radius: 0 !important; /* Zero cantos arredondados: corte reto de estilete */
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            display: flex;
            min-height: 100vh;
            background: var(--lambe-bg);
            color: var(--text-light);
            overflow-x: hidden;
        }

        /* Scrollbar estilo xerox industrial */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        ::-webkit-scrollbar-track {
            background: #0a0a0c;
            border-left: 1px solid #000;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--tint-yellow);
            border: 2px solid #000;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--tint-magenta);
        }

        /* Sidebar: Muro / Lambe Cartaz */
        .admin-sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            color: var(--text-light);
            display: flex;
            flex-direction: column;
            border-right: 3px solid #000000;
            box-shadow: 5px 0 0 rgba(0, 0, 0, 0.4);
            position: relative;
            z-index: 20;
        }

        /* Tarja de advertência no topo */
        .admin-sidebar::before {
            content: '';
            display: block;
            height: 6px;
            width: 100%;
            background: repeating-linear-gradient(45deg, #ffe600, #ffe600 10px, #000000 10px, #000000 20px);
        }

        .sidebar-header {
            padding: 1.5rem 1.25rem 1.25rem;
            background: var(--lambe-surface);
            border-bottom: 2px solid #000000;
            position: relative;
        }

        /* Fita crepe decorativa */
        .sidebar-header::after {
            content: 'RESISTÊNCIA DIY';
            position: absolute;
            top: -8px;
            right: 12px;
            background: var(--tape-cream);
            color: #000;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 8px;
            border: 1px solid rgba(0,0,0,0.3);
            box-shadow: 1px 1px 2px rgba(0,0,0,0.2);
            transform: rotate(2deg);
            letter-spacing: 0.1em;
        }

        .sidebar-header h1 {
            margin: 0;
            font-family: 'Bebas Neue', 'Archivo Black', sans-serif;
            font-size: 1.85rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--lambe-paper);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-header h1 .logo-dot {
            width: 12px;
            height: 12px;
            background: var(--tint-magenta);
            border: 2px solid #000;
            display: inline-block;
        }

        .sidebar-header .hud-badge {
            display: inline-block;
            margin-top: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            color: #000;
            background: var(--tint-yellow);
            border: 1px solid #000;
            padding: 2px 8px;
            box-shadow: 2px 2px 0 #000;
            text-transform: uppercase;
        }

        .admin-nav {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: 1rem 0;
        }

        .nav-section-title {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--text-dim);
            padding: 0.75rem 1.25rem 0.35rem;
        }

        .admin-nav a {
            color: var(--text-muted);
            text-decoration: none;
            padding: 0.85rem 1.25rem;
            margin: 2px 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            font-family: 'JetBrains Mono', monospace;
            transition: all 0.15s ease;
            border: 2px solid transparent;
            position: relative;
        }

        .admin-nav a .nav-index {
            font-family: 'Bebas Neue', monospace;
            font-size: 1.1rem;
            color: var(--text-dim);
            transition: color 0.15s;
        }

        .admin-nav a:hover {
            color: #000;
            background: var(--lambe-paper);
            border: 2px solid #000;
            box-shadow: 3px 3px 0 #000;
            transform: translate(-2px, -2px);
        }

        .admin-nav a:hover .nav-index {
            color: #000;
        }

        /* Cartaz colado ativo */
        .admin-nav a.active {
            color: #000000;
            background: var(--tint-yellow);
            border: 2px solid #000000;
            box-shadow: 4px 4px 0 #000000;
            font-weight: 800;
            transform: translate(-2px, -2px);
        }

        .admin-nav a.active .nav-index {
            color: var(--tint-magenta);
            font-weight: 800;
            text-shadow: 1px 1px 0 #000;
        }

        .channels-accordion {
            background: #0d0e11;
            margin: 0.25rem 10px 0.75rem;
            padding: 0.5rem 0;
            border: 2px dashed #333;
        }

        .admin-nav a.logout-link {
            margin-top: auto;
            border-top: 2px solid #000;
            color: var(--tint-red);
        }

        .admin-nav a.logout-link:hover {
            background: var(--tint-red);
            color: #ffffff;
            border-color: #000;
        }

        .admin-content {
            flex: 1;
            background: var(--lambe-bg);
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
            <span class="hud-badge">TRINCHEIRA // DESCENTRALIZADA</span>
        </div>
        <nav class="admin-nav">
            <span class="nav-section-title">MÓDULOS DE COMBATE</span>
            <a href="/admin/microsub" class="<?= $activeTab === 'microsub' ? 'active' : '' ?>">
                <span class="nav-index">01</span> Timeline
            </a>
            <?php if ($activeTab === 'microsub'): ?>
                <div class="channels-accordion">
                    <?php foreach ($channels as $ch): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-right: 0.75rem;">
                            <a href="/admin/microsub?channel=<?= urlencode($ch['uid']) ?>" style="flex: 1; padding: 0.45rem 1rem 0.45rem 1.5rem; font-size: 0.82rem; <?= $currentChannel === $ch['uid'] ? 'color: #000; background: var(--tint-cyan); border: 2px solid #000; font-weight: 800; box-shadow: 2px 2px 0 #000;' : 'border: 2px solid transparent;' ?>">
                                # <?= htmlspecialchars($ch['name']) ?>
                            </a>
                            <?php if ($ch['uid'] !== 'inbox' && $ch['uid'] !== 'notifications'): ?>
                                <button onclick="deleteChannel('<?= htmlspecialchars($ch['uid']) ?>', '<?= htmlspecialchars(addslashes($ch['name'])) ?>')" style="background: none; border: none; color: var(--tint-red); cursor: pointer; padding: 0 0.5rem; font-size: 1.1em; font-weight: bold;" title="Remover canal">&times;</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <a href="#" onclick="createChannel(event)" style="padding: 0.45rem 1rem 0.45rem 1.5rem; font-size: 0.82rem; color: var(--tint-magenta); border: 2px dashed var(--tint-magenta); margin: 6px 10px 2px; text-align: center; font-weight: 800;">
                        + NOVO CANAL
                    </a>
                </div>
                <script>
                    async function deleteChannel(uid, name) {
                        if (!confirm('Deseja realmente remover o canal "' + name + '"?')) return;
                        try {
                            const res = await fetch('/microsub', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({ action: 'channels', method: 'delete', uid: uid })
                            });
                            if (res.ok) {
                                window.location.href = '/admin/microsub';
                            } else {
                                alert("Falha ao remover canal.");
                            }
                        } catch (err) {
                            console.error(err);
                            alert("Falha ao remover canal.");
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
                                alert("Falha ao criar canal.");
                            }
                        } catch (err) {
                            console.error(err);
                            alert("Falha ao criar canal.");
                        }
                    }
                </script>
            <?php endif; ?>
            <a href="/admin/moderation" class="<?= $activeTab === 'moderation' ? 'active' : '' ?>">
                <span class="nav-index">02</span> Moderação
            </a>
            <a href="/admin/micropub" class="<?= $activeTab === 'micropub' ? 'active' : '' ?>">
                <span class="nav-index">03</span> Publicador
            </a>
            <a href="/admin/config" class="<?= $activeTab === 'config' ? 'active' : '' ?>">
                <span class="nav-index">04</span> Configurações
            </a>
            <a href="/admin/config?action=logout" class="logout-link">
                <span class="nav-index">05</span> Desconectar
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
