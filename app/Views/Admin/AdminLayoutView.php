<?php

declare(strict_types=1);

namespace Indieinabox\Views\Admin;

use Indieinabox\Core\Database;
use PDO;

/**
 * Presentation view component that renders the administrative shell layout and navigation sidebar.
 * Estética: Lambe-Lambe Periférico, Dissidência Travesti & Resistência DIY (Neo-Brutalismo Urbano).
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
    <title>Indieinabox Admin // Trincheira DIY & Lambe-Lambe</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Bebas+Neue&family=Outfit:wght@400;600;800&family=JetBrains+Mono:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Estética Lambe-Lambe Periférico & Neo-Brutalismo DIY */
            --lambe-bg: #121316;            /* Asfalto / Muro Noturno */
            --lambe-paper: #f4efe6;         /* Papel Sulfite Cru */
            --lambe-paper-dark: #e8dfcf;    /* Papel Kraft / Sulfite Envelhecido */
            --lambe-surface: #1e1f25;       /* Camada de cartaz escuro */
            --lambe-surface-2: #282a32;
            --lambe-ink-black: #0a0a0c;     /* Tinta preta pura / carimbo */
            --tint-magenta: #ff007f;        /* Rosa Choque / Travesti / Resistência */
            --tint-yellow: #ffe600;         /* Amarelo Alerta de Rua */
            --tint-cyan: #00e5ff;           /* Ciano Estêncil */
            --tint-red: #ff2244;            /* Vermelho Rebeldia / Alerta */
            --tint-green: #00ff66;          /* Verde Spray */
            --tape-cream: rgba(246, 236, 190, 0.9); /* Fita Crepe Translúcida */
            --border-hard: 2px solid #000000;
            --border-hard-thick: 3px solid #000000;
            --stamp-shadow: 4px 4px 0px #000000;
            --stamp-shadow-sm: 2px 2px 0px #000000;
            --stamp-shadow-lg: 6px 6px 0px #000000;
            --text-light: #f4efe6;
            --text-dark: #0a0a0c;
            --text-muted: #a0a4b0;
            --text-dim: #727684;
            --sidebar-bg: #121316;
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
            line-height: 1.6;
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
            z-index: 100;
            flex-shrink: 0;
            min-height: 100vh;
        }

        /* Tarja de advertência no topo da sidebar */
        .admin-sidebar::before {
            content: '';
            display: block;
            height: 6px;
            width: 100%;
            background: repeating-linear-gradient(45deg, #ffe600, #ffe600 10px, #000000 10px, #000000 20px);
        }

        .sidebar-header {
            padding: 1.5rem 1.25rem 1.25rem;
            background: #18191f;
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

        .sidebar-header h1 a {
            color: inherit;
            text-decoration: none;
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

        .sidebar-header .doc-link {
            display: inline-block;
            margin-top: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--tint-cyan);
            text-decoration: none;
            letter-spacing: 0.05em;
        }
        .sidebar-header .doc-link:hover {
            color: var(--tint-yellow);
            text-decoration: underline;
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
            font-size: 0.92rem;
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
            padding: 0.75rem 0.25rem;
            border: 2px dashed #333;
        }

        /* Fitas adesivas de canais */
        .tape-channel {
            display: block;
            background: var(--tape-cream);
            color: #000000 !important;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 800;
            font-size: 0.82rem;
            padding: 7px 12px;
            margin: 5px 8px;
            border: 1px solid rgba(0,0,0,0.25);
            box-shadow: 2px 2px 0 rgba(0,0,0,0.25);
            transform: rotate(-1deg);
            text-decoration: none;
            transition: all 0.1s ease;
        }
        .tape-channel:nth-child(even) {
            transform: rotate(1.5deg);
        }
        .tape-channel:hover {
            background: #ffffff;
            transform: translate(-1px, -1px) scale(1.02);
            box-shadow: 3px 3px 0 #000000;
            color: #000 !important;
        }
        .tape-channel.active {
            background: var(--tint-yellow);
            font-weight: 900;
            border: 2px solid #000000;
            box-shadow: 3px 3px 0 #000000;
            transform: rotate(0deg) scale(1.03);
            color: #000 !important;
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

        /* =========================================================
           Componentes Globais Neo-Brutalistas / Lambe-Lambe Periférico
           ========================================================= */

        /* Cartaz de Parede (Wheatpaste Poster) */
        .card-lambe, .lambe-poster {
            background: var(--lambe-paper);
            color: var(--lambe-ink-black);
            border: var(--border-hard);
            box-shadow: var(--stamp-shadow);
            padding: 1.75rem;
            margin-bottom: 2rem;
            position: relative;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .card-lambe:hover, .lambe-poster:hover {
            transform: translate(-1px, -1px);
            box-shadow: var(--stamp-shadow-lg);
        }

        /* Fitas adesivas nos cantos dos cartazes */
        .has-tape::before,
        .tape-corner-tl {
            content: '';
            position: absolute;
            top: -9px;
            left: -12px;
            width: 52px;
            height: 20px;
            background: var(--tape-cream);
            border: 1px solid rgba(0,0,0,0.18);
            box-shadow: 1px 1px 2px rgba(0,0,0,0.15);
            transform: rotate(-18deg);
            z-index: 5;
            pointer-events: none;
        }
        .has-tape::after,
        .tape-corner-tr {
            content: '';
            position: absolute;
            top: -9px;
            right: -12px;
            width: 52px;
            height: 20px;
            background: var(--tape-cream);
            border: 1px solid rgba(0,0,0,0.18);
            box-shadow: 1px 1px 2px rgba(0,0,0,0.15);
            transform: rotate(18deg);
            z-index: 5;
            pointer-events: none;
        }
        .tape-corner-bl {
            position: absolute;
            bottom: -9px;
            left: -12px;
            width: 52px;
            height: 20px;
            background: var(--tape-cream);
            border: 1px solid rgba(0,0,0,0.18);
            box-shadow: 1px 1px 2px rgba(0,0,0,0.15);
            transform: rotate(18deg);
            z-index: 5;
            pointer-events: none;
        }
        .tape-corner-br {
            position: absolute;
            bottom: -9px;
            right: -12px;
            width: 52px;
            height: 20px;
            background: var(--tape-cream);
            border: 1px solid rgba(0,0,0,0.18);
            box-shadow: 1px 1px 2px rgba(0,0,0,0.15);
            transform: rotate(-18deg);
            z-index: 5;
            pointer-events: none;
        }
        .tape-top-center {
            position: absolute;
            top: -12px;
            left: 50%;
            width: 75px;
            height: 22px;
            margin-left: -37px;
            background: var(--tape-cream);
            border: 1px solid rgba(0,0,0,0.2);
            box-shadow: 1px 1px 2px rgba(0,0,0,0.15);
            transform: rotate(-1.5deg);
            z-index: 5;
            pointer-events: none;
        }

        /* Tarja de Advertência Zebrada (Hazard Stripes) */
        .hazard-stripes, .warning-stripe {
            height: 14px;
            width: 100%;
            background: repeating-linear-gradient(-45deg, #ffe600, #ffe600 10px, #000000 10px, #000000 20px);
            border-top: 2px solid #000000;
            border-bottom: 2px solid #000000;
            margin: 0.75rem 0 1.25rem;
            display: block;
        }

        /* Botões Carimbo */
        .btn-stamp {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 10px 18px;
            border: 2px solid #000000;
            box-shadow: var(--stamp-shadow);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.85rem;
            background: var(--lambe-paper);
            color: #000000;
            transition: all 0.1s ease;
        }
        .btn-stamp:hover {
            transform: translate(-1px, -1px);
            box-shadow: 5px 5px 0 #000;
        }
        .btn-stamp:active {
            transform: translate(2px, 2px);
            box-shadow: 1px 1px 0 #000;
        }
        .btn-stamp-primary, .btn-stamp-magenta {
            background: var(--tint-magenta) !important;
            color: #000000 !important;
        }
        .btn-stamp-yellow {
            background: var(--tint-yellow) !important;
            color: #000000 !important;
        }
        .btn-stamp-cyan {
            background: var(--tint-cyan) !important;
            color: #000000 !important;
        }
        .btn-stamp-red {
            background: var(--tint-red) !important;
            color: #ffffff !important;
        }
        .btn-stamp-dark {
            background: #0a0a0c !important;
            color: #f4efe6 !important;
            border-color: #000 !important;
        }

        /* Carimbos de Borracha Serigráficos (Rubber Stamps) */
        .stamp-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 900;
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 3px 8px;
            border: 2px solid currentColor;
            box-shadow: 2px 2px 0 rgba(0,0,0,0.3);
            background: #ffffff;
            line-height: 1.2;
            cursor: default;
        }
        .stamp-suspend, .stamp-red {
            color: #ff0055;
            border-color: #ff0055;
            background: #fff0f5;
        }
        .stamp-silence, .stamp-yellow {
            color: #000000;
            border-color: #000000;
            background: #ffe600;
        }
        .stamp-filter, .stamp-cyan {
            color: #007799;
            border-color: #007799;
            background: #e6faff;
        }
        .stamp-approve, .stamp-green {
            color: #008833;
            border-color: #008833;
            background: #e8faed;
        }

        /* Badges & Selos */
        .badge-stamp {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 3px 8px;
            border: 1px solid #000;
            box-shadow: 2px 2px 0 #000;
            display: inline-block;
            background: var(--lambe-paper);
            color: #000;
        }
        .badge-magenta {
            background: var(--tint-magenta);
            color: #000;
        }
        .badge-yellow {
            background: var(--tint-yellow);
            color: #000;
        }
        .badge-cyan {
            background: var(--tint-cyan);
            color: #000;
        }
        .badge-red {
            background: var(--tint-red);
            color: #fff;
        }

        /* Inputs, Textareas, Selects - Estilo Formulário de Papel / Xerox */
        input[type="text"],
        input[type="url"],
        input[type="password"],
        input[type="email"],
        input[type="number"],
        textarea,
        select {
            background: var(--lambe-paper-dark) !important;
            border: 2px solid #000000 !important;
            color: #000000 !important;
            font-family: 'JetBrains Mono', monospace !important;
            padding: 10px 14px !important;
            box-shadow: inset 1px 1px 0 rgba(0,0,0,0.2) !important;
            outline: none !important;
            font-size: 0.9rem !important;
            font-weight: 600 !important;
            transition: border-color 0.15s, box-shadow 0.15s, background-color 0.15s !important;
        }
        input[type="text"]:focus,
        input[type="url"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus,
        input[type="number"]:focus,
        textarea:focus,
        select:focus {
            border-color: #000000 !important;
            background: #ffffff !important;
            box-shadow: 3px 3px 0 #000000 !important;
        }

        /* Tabelas Serigráficas / Cyber Table */
        .cyber-table-wrap {
            overflow-x: auto;
            border: 2px solid #000000;
            box-shadow: 4px 4px 0 #000000;
            margin: 1.5rem 0;
            background: var(--lambe-paper);
        }
        .cyber-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            text-align: left;
            color: #000000;
        }
        .cyber-table th {
            background: #000000;
            color: var(--tint-yellow);
            font-family: 'Bebas Neue', 'Archivo Black', sans-serif;
            font-size: 1.15rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 10px 14px;
            border-bottom: 2px solid #000000;
        }
        .cyber-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #cfc6b4;
            color: #000000;
            background: var(--lambe-paper);
        }
        .cyber-table tr:hover td {
            background: #ece4d4;
        }

        /* Tarja de Advertência */
        .warning-stripe {
            height: 6px;
            width: 100%;
            background: repeating-linear-gradient(45deg, #ffe600, #ffe600 10px, #000000 10px, #000000 20px);
            margin: 1rem 0;
        }

        /* Fita Crepe Tag */
        .tape-label {
            position: absolute;
            top: -10px;
            right: 14px;
            background: var(--tape-cream);
            color: #000;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.68rem;
            font-weight: 800;
            padding: 2px 10px;
            border: 1px solid rgba(0,0,0,0.4);
            box-shadow: 2px 2px 0 rgba(0,0,0,0.3);
            transform: rotate(2deg);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        /* Mobile Drawer */
        .mobile-nav-toggle {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            background: var(--tint-yellow);
            color: #000;
            font-family: 'Archivo Black', sans-serif;
            font-size: 0.9rem;
            padding: 12px 20px;
            border: 2px solid #000;
            box-shadow: 4px 4px 0 #000;
            cursor: pointer;
            text-transform: uppercase;
        }

        @media (max-width: 900px) {
            .mobile-nav-toggle {
                display: block;
            }
            .admin-sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                transform: translateX(-100%);
                transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            }
            .admin-sidebar.open {
                transform: translateX(0);
            }
            .admin-content {
                width: 100%;
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-nav-toggle" onclick="document.querySelector('.admin-sidebar').classList.toggle('open')" aria-label="Toggle navigation">☰ PAINEL</button>

    <div class="admin-sidebar">
        <div class="sidebar-header">
            <h1><a href="/"><span class="logo-dot"></span>Indieinabox</a></h1>
            <span class="hud-badge">TRINCHEIRA // DESCENTRALIZADA</span>
            <div>
                <a href="https://indieinabox.2lp.in" target="_blank" rel="noopener" class="doc-link">📖 DOCUMENTAÇÃO ↗</a>
            </div>
        </div>
        <nav class="admin-nav">
            <span class="nav-section-title">MÓDULOS DE COMBATE</span>
            <a href="/admin/microsub" class="<?= $activeTab === 'microsub' ? 'active' : '' ?>">
                <span class="nav-index">01</span> Timeline
            </a>
            <?php if ($activeTab === 'microsub'): ?>
                <div class="channels-accordion">
                    <?php foreach ($channels as $ch): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin: 2px 4px;">
                            <a href="/admin/microsub?channel=<?= urlencode($ch['uid']) ?>" class="tape-channel <?= $currentChannel === $ch['uid'] ? 'active' : '' ?>" style="flex: 1;">
                                # <?= htmlspecialchars($ch['name']) ?>
                            </a>
                            <?php if ($ch['uid'] !== 'inbox' && $ch['uid'] !== 'notifications'): ?>
                                <button onclick="deleteChannel('<?= htmlspecialchars($ch['uid']) ?>', '<?= htmlspecialchars(addslashes($ch['name'])) ?>')" style="background: none; border: none; color: var(--tint-red); cursor: pointer; padding: 0 0.4rem; font-size: 1.1em; font-weight: bold;" title="Remover canal">&times;</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <a href="#" onclick="createChannel(event)" style="display: block; padding: 0.45rem 1rem; font-size: 0.78rem; color: var(--tint-magenta); border: 2px dashed var(--tint-magenta); margin: 8px 12px 4px; text-align: center; font-weight: 800; font-family: 'JetBrains Mono', monospace; text-transform: uppercase;">
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
        <footer class="admin-footer" style="margin-top: 4rem; padding: 2rem 0; border-top: 2px solid #000000; text-align: center; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: #555555;">
            <span>IndieInABox Cockpit &bull; Sistema de Resistência &amp; Publicação Periférica</span>
        </footer>
    </div>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }
}
