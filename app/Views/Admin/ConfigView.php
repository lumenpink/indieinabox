<?php

declare(strict_types=1);

namespace Indieinabox\Views\Admin;

use Indieinabox\Site\Site;

/**
 * Presentation view component that renders the configuration and bootstrap views.
 */
class ConfigView
{
    /**
     * Renders the bootstrap first-run setup form.
     */
    public static function renderBootstrap(?string $error = null): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $detectedFqdn = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080');
        ob_start();
        ?>
<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Bootstrap Setup - Indieinabox</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
            <style>
                :root {
                    --cyber-bg: #090b10;
                    --cyber-surface: #0f141f;
                    --neon-pink: #ff2a85;
                    --neon-cyan: #00f0ff;
                    --text-primary: #f8fafc;
                    --text-muted: #94a3b8;
                    --border: rgba(0, 240, 255, 0.25);
                }
                * {
                    box-sizing: border-box;
                }
                body {
                    background: radial-gradient(circle at 15% 20%, rgba(255, 42, 133, 0.08) 0%, transparent 45%),
                                radial-gradient(circle at 85% 80%, rgba(0, 240, 255, 0.08) 0%, transparent 50%),
                                linear-gradient(145deg, #090b10 0%, #0d121c 60%, #121826 100%);
                    color: var(--text-primary);
                    font-family: 'Outfit', sans-serif;
                    line-height: 1.6;
                    min-height: 100vh;
                    margin: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                }
                .setup-container {
                    background: rgba(15, 20, 31, 0.9);
                    border: 1px solid var(--border);
                    border-radius: 8px;
                    max-width: 520px;
                    width: 100%;
                    padding: 2.5rem;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6), 0 0 20px rgba(0, 240, 255, 0.1);
                    position: relative;
                }
                .setup-container::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 2px;
                    background: linear-gradient(90deg, var(--neon-pink) 0%, var(--neon-cyan) 100%);
                }
                .badge {
                    font-family: 'JetBrains Mono', monospace;
                    font-size: 0.72rem;
                    color: var(--neon-cyan);
                    border: 1px solid rgba(0, 240, 255, 0.3);
                    background: rgba(0, 240, 255, 0.08);
                    padding: 2px 8px;
                    border-radius: 3px;
                    text-transform: uppercase;
                    letter-spacing: 0.1em;
                    display: inline-block;
                    margin-bottom: 0.75rem;
                }
                h1 {
                    color: #ffffff;
                    margin: 0 0 0.5rem;
                    font-size: 1.75rem;
                    font-weight: 800;
                    letter-spacing: 0.04em;
                    text-shadow: 0 0 12px rgba(255, 42, 133, 0.4);
                }
                p.intro {
                    color: var(--text-muted);
                    font-size: 0.92rem;
                    margin: 0 0 1.75rem;
                }
                .error-message {
                    color: #fda4af;
                    background: rgba(244, 63, 94, 0.15);
                    border: 1px solid rgba(244, 63, 94, 0.4);
                    padding: 0.75rem 1rem;
                    border-radius: 4px;
                    margin-bottom: 1.25rem;
                    font-family: 'JetBrains Mono', monospace;
                    font-size: 0.85rem;
                }
                .form-group {
                    margin-bottom: 1.25rem;
                }
                label {
                    display: block;
                    font-weight: 600;
                    font-size: 0.88rem;
                    margin-bottom: 0.4rem;
                    color: #e2e8f0;
                }
                input {
                    background: #090c13;
                    border: 1px solid var(--border);
                    color: #ffffff;
                    padding: 10px 14px;
                    font-family: 'JetBrains Mono', monospace;
                    font-size: 0.9rem;
                    width: 100%;
                    border-radius: 4px;
                    transition: border-color 0.2s, box-shadow 0.2s;
                }
                input:focus {
                    border-color: var(--neon-cyan);
                    box-shadow: 0 0 12px rgba(0, 240, 255, 0.35);
                    outline: none;
                }
                button {
                    background: var(--neon-pink);
                    color: #ffffff;
                    border: none;
                    padding: 12px 20px;
                    font-family: 'JetBrains Mono', monospace;
                    font-size: 0.95rem;
                    font-weight: 700;
                    cursor: pointer;
                    width: 100%;
                    border-radius: 4px;
                    margin-top: 1rem;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    box-shadow: 0 0 16px rgba(255, 42, 133, 0.4);
                    transition: all 0.2s;
                }
                button:hover {
                    box-shadow: 0 0 24px rgba(255, 42, 133, 0.7);
                    transform: translateY(-1px);
                }
            </style>
        </head>
        <body>
            <div class="setup-container">
                <span class="badge">SYS // FIRST-RUN</span>
                <h1>Initialize Indieinabox</h1>
                <p class="intro">Configure administrative credentials and site identity to initialize your instance.</p>

                <?php if ($error): ?>
                    <div class="error-message"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form action="" method="POST">
                    <div class="form-group">
                        <label for="indieauth_password">IndieAuth Password</label>
                        <input type="password" name="indieauth_password" id="indieauth_password" required placeholder="••••••••" autofocus>
                    </div>

                    <div class="form-group">
                        <label for="sitename">Site Name</label>
                        <input type="text" name="sitename" id="sitename" value="Aaron Schwartz's new social network" required>
                    </div>

                    <div class="form-group">
                        <label for="fqdn">Site FQDN (URL)</label>
                        <input type="url" name="fqdn" id="fqdn" value="<?= htmlspecialchars($detectedFqdn) ?>" required>
                    </div>

                    <button type="submit">Configure & Rebuild</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Renders the main administration configuration form tabs.
     *
     * @param Site $site
     * @param array<string, mixed> $config
     * @param string|null $message
     * @param string|null $error
     * @return string
     */
    public static function renderConfig(Site $site, array $config, ?string $message = null, ?string $error = null): string
    {
        $langArr = $config['lang'] ?? ['en'];
        if (!is_array($langArr)) {
            $langArr = [$langArr];
        }
        $prettyLinksActive = $config['prettylinks'] ?? true;
        $fqdn = rtrim($site->metadata->fqdn ?? '', '/');
        $kinds = $config['kinds'] ?? [];

        $themesDir = \Indieinabox\Core\Database::$dataDir . '/themes';
        $availableThemes = ['default'];
        if (is_dir($themesDir)) {
            $items = scandir($themesDir) ?: [];
            foreach ($items as $item) {
                if ($item !== '.' && $item !== '..' && is_dir($themesDir . '/' . $item)) {
                    $availableThemes[] = $item;
                }
            }
        }
        $activeTheme = $config['active_theme'] ?? 'default';

        $officialThemes = [
            '' => '-- Select Theme --',
            'https://github.com/lumen/theme-minimal/archive/refs/heads/main.zip' => 'Minimal Theme',
            'https://github.com/lumen/theme-dark/archive/refs/heads/main.zip' => 'Dark Theme'
        ];

        $globalStrings = [
            'Home' => 'Home link',
            'Index' => 'Index link',
            'Now' => 'Now link',
            'Recent posts' => 'Recent posts header',
            'Browse the sections of the site in Gopher style:' => 'Gopher section description',
            'About' => 'About link',
            'Maturity' => 'Maturity label',
            'Reliability' => 'Reliability label',
            'Shortlink' => 'Shortlink label',
            'Like' => 'Singular for Like',
            'Likes' => 'Plural for Likes',
            'Repost' => 'Singular for Repost',
            'Reposts' => 'Plural for Reposts',
            'Reply' => 'Singular for Reply',
            'Replies' => 'Plural for Replies',
            'Interactions on' => 'Interactions page title prefix',
            'Permalink' => 'Permalink text in replies',
            'Flowerbed' => 'Flowerbed label for garden posts',
            'Confidence' => 'Confidence label for garden posts',
            'Importance' => 'Importance label for garden posts',
            'Also on' => 'Syndication links prefix',
            'In reply to' => 'IndieWeb context prefix',
            'Liked' => 'IndieWeb context prefix',
            'Reposted' => 'IndieWeb context prefix',
            'Bookmarked' => 'IndieWeb context prefix',
            'Watched' => 'IndieWeb context prefix',
            'Read' => 'IndieWeb context prefix',
            'Listened to' => 'IndieWeb context prefix',
            'This page was automatically translated by AI.' => 'AI translation notice',
            'This page was automatically translated by AI and revised by a human.' => 'AI translation notice (revised)',
            'Read more' => 'Read more link',
            'general' => 'Default flowerbed',
            'certain' => 'Confidence level',
            'likely' => 'Confidence level',
            'possible' => 'Confidence level',
            'unlikely' => 'Confidence level',
            'impossible' => 'Confidence level',
            'sprout' => 'Maturity level',
            'seedling' => 'Maturity level',
            'tree' => 'Maturity level',
            'wilted' => 'Maturity level',
            'stone' => 'Maturity level',
            'trivial' => 'Importance level',
            'minor' => 'Importance level',
            'moderate' => 'Importance level',
            'major' => 'Importance level',
            'critical' => 'Importance level',
            'unknown' => 'Fallback term',
            'Tag' => 'Tag singular label',
            'Tags' => 'Tag plural label',
            'Flowerbeds' => 'Flowerbed plural label'
        ];

        ob_start();
        ?>
        <style>
            .config-wrapper {
                max-width: 1100px;
                margin: 0 auto;
                padding: 2.5rem 2rem 5rem;
                font-family: 'Outfit', sans-serif;
                color: var(--text-light, #f8fafc);
            }
            .config-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 2rem;
                border-bottom: 1px solid var(--cyber-border, rgba(0, 240, 255, 0.22));
                padding-bottom: 1.25rem;
            }
            .config-header h1 {
                margin: 0;
                font-size: 1.75rem;
                font-weight: 800;
                color: #ffffff;
                letter-spacing: 0.04em;
                text-shadow: 0 0 12px var(--neon-pink-glow, rgba(255, 42, 133, 0.4));
            }
            .config-header .config-subtitle {
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.72rem;
                color: var(--neon-cyan, #00f0ff);
                margin-top: 4px;
                letter-spacing: 0.1em;
            }
            .config-header .logout-btn {
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.8rem;
                color: #f43f5e;
                border: 1px solid rgba(244, 63, 94, 0.5);
                padding: 6px 14px;
                border-radius: 4px;
                text-decoration: none;
                transition: all 0.2s;
            }
            .config-header .logout-btn:hover {
                background: rgba(244, 63, 94, 0.15);
                color: #fff;
                border-color: #f43f5e;
            }
            .alert-saved {
                background: rgba(0, 255, 159, 0.12);
                border: 1px solid var(--neon-green, #00ff9f);
                color: var(--neon-green, #00ff9f);
                padding: 0.85rem 1.25rem;
                border-radius: 4px;
                margin-bottom: 1.5rem;
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.88rem;
                box-shadow: 0 0 14px rgba(0, 255, 159, 0.25);
            }
            /* Cyber Tab Strip */
            .config-tabs {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                margin-bottom: 2rem;
                border-bottom: 1px solid rgba(0, 240, 255, 0.25);
                padding-bottom: 0;
            }
            .config-tab-btn {
                background: rgba(13, 18, 28, 0.7);
                border: 1px solid rgba(0, 240, 255, 0.2);
                border-bottom: none;
                color: var(--text-muted, #94a3b8);
                padding: 10px 18px;
                font-family: 'Outfit', sans-serif;
                font-size: 0.92rem;
                font-weight: 600;
                cursor: pointer;
                border-radius: 6px 6px 0 0;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                gap: 8px;
                position: relative;
            }
            .config-tab-btn .tab-num {
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.7rem;
                color: var(--text-dim, #64748b);
            }
            .config-tab-btn:hover {
                color: var(--neon-cyan, #00f0ff);
                background: rgba(0, 240, 255, 0.08);
                border-color: rgba(0, 240, 255, 0.45);
            }
            .config-tab-btn:hover .tab-num {
                color: var(--neon-cyan, #00f0ff);
            }
            .config-tab-btn.active {
                background: linear-gradient(180deg, rgba(255, 42, 133, 0.18) 0%, rgba(15, 20, 31, 0.95) 100%);
                border-color: var(--neon-pink, #ff2a85);
                color: #ffffff;
                text-shadow: 0 0 8px rgba(255, 42, 133, 0.4);
                border-bottom: 2px solid var(--neon-pink, #ff2a85);
                margin-bottom: -1px;
            }
            .config-tab-btn.active .tab-num {
                color: var(--neon-pink, #ff2a85);
                text-shadow: 0 0 6px var(--neon-pink-glow, rgba(255, 42, 133, 0.4));
            }
            /* Tab Content Hiding & Animation */
            .tab-content {
                display: none;
            }
            .tab-content.active {
                display: block;
                animation: cyberTabFade 0.2s ease-in-out;
            }
            @keyframes cyberTabFade {
                from { opacity: 0; transform: translateY(4px); }
                to { opacity: 1; transform: translateY(0); }
            }
            /* Fieldsets / Cards */
            fieldset {
                background: rgba(15, 20, 31, 0.85);
                border: 1px solid rgba(0, 240, 255, 0.22);
                border-radius: 6px;
                padding: 1.75rem;
                margin-bottom: 1.75rem;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            }
            legend {
                font-family: 'Outfit', sans-serif;
                font-size: 1rem;
                font-weight: 700;
                color: #ffffff;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                padding: 4px 12px;
                background: #0d121c;
                border: 1px solid rgba(0, 240, 255, 0.35);
                border-radius: 4px;
                text-shadow: 0 0 8px rgba(0, 240, 255, 0.35);
            }
            .grid-2 {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 1.25rem;
            }
            .form-group {
                margin-bottom: 1.25rem;
            }
            .form-group label {
                display: block;
                font-weight: 600;
                font-size: 0.88rem;
                margin-bottom: 0.4rem;
                color: #e2e8f0;
                letter-spacing: 0.02em;
            }
            .form-group small, .form-group .help {
                display: block;
                font-size: 0.8rem;
                color: var(--text-muted, #94a3b8);
                margin-top: 0.35rem;
            }
            input[type="text"],
            input[type="password"],
            input[type="url"],
            input[type="number"],
            select,
            textarea {
                width: 100%;
                background: #090c13;
                border: 1px solid rgba(0, 240, 255, 0.25);
                color: #ffffff;
                padding: 9px 12px;
                border-radius: 4px;
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.88rem;
                box-sizing: border-box;
                transition: border-color 0.2s, box-shadow 0.2s;
            }
            input:focus, select:focus, textarea:focus {
                border-color: var(--neon-cyan, #00f0ff);
                box-shadow: 0 0 10px rgba(0, 240, 255, 0.35);
                outline: none;
            }
            textarea {
                min-height: 100px;
                resize: vertical;
            }
            .checkbox-group {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 0.75rem;
            }
            .checkbox-group input[type="checkbox"] {
                accent-color: var(--neon-pink, #ff2a85);
                width: 16px;
                height: 16px;
                cursor: pointer;
            }
            .checkbox-group label {
                margin: 0;
                cursor: pointer;
                font-weight: 500;
                font-size: 0.9rem;
                color: #e2e8f0;
            }
            .color-picker {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .color-picker input[type="color"] {
                background: transparent;
                border: 1px solid rgba(0, 240, 255, 0.3);
                border-radius: 4px;
                width: 36px;
                height: 36px;
                cursor: pointer;
                padding: 2px;
            }
            .kind-card {
                background: rgba(9, 13, 20, 0.7);
                border: 1px solid rgba(0, 240, 255, 0.2);
                border-radius: 6px;
                padding: 1.25rem;
                margin-bottom: 1.25rem;
            }
            .kind-card h3 {
                margin: 0 0 1rem;
                font-size: 1.1rem;
                color: var(--neon-cyan, #00f0ff);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .btn-secondary {
                background: transparent;
                border: 1px solid rgba(0, 240, 255, 0.4);
                color: var(--neon-cyan, #00f0ff);
                padding: 5px 12px;
                border-radius: 4px;
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.8rem;
                cursor: pointer;
                transition: all 0.2s;
            }
            .btn-secondary:hover:not(:disabled) {
                background: rgba(0, 240, 255, 0.15);
                border-color: var(--neon-cyan, #00f0ff);
                color: #ffffff;
            }
            .btn-secondary:disabled {
                opacity: 0.35;
                cursor: not-allowed;
            }
            .tech-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 1rem;
                border: 1px solid rgba(0, 240, 255, 0.2);
                font-size: 0.88rem;
            }
            .tech-table th {
                background: rgba(0, 240, 255, 0.08);
                color: var(--neon-cyan, #00f0ff);
                padding: 9px 12px;
                text-align: left;
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.78rem;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                border-bottom: 1px solid rgba(0, 240, 255, 0.25);
            }
            .tech-table td {
                padding: 9px 12px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            }
            .submit-group {
                position: sticky;
                bottom: 0;
                background: rgba(9, 13, 20, 0.95);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                padding: 1.25rem 1.5rem;
                border-top: 1px solid rgba(0, 240, 255, 0.3);
                box-shadow: 0 -8px 25px rgba(0, 0, 0, 0.6);
                z-index: 100;
                margin-top: 2.5rem;
                border-radius: 6px;
                display: flex;
                gap: 12px;
            }
            .btn-action-rebuild {
                flex: 1;
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.92rem;
                font-weight: 700;
                padding: 14px;
                background: transparent;
                border: 1px solid var(--neon-cyan, #00f0ff);
                color: var(--neon-cyan, #00f0ff);
                border-radius: 4px;
                cursor: pointer;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                transition: all 0.2s;
            }
            .btn-action-rebuild:hover {
                background: rgba(0, 240, 255, 0.15);
                box-shadow: 0 0 14px rgba(0, 240, 255, 0.35);
            }
            .btn-action-save {
                flex: 2;
                font-family: 'JetBrains Mono', monospace;
                font-size: 0.95rem;
                font-weight: 700;
                padding: 14px;
                background: var(--neon-pink, #ff2a85);
                color: #ffffff;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                box-shadow: 0 0 16px var(--neon-pink-glow, rgba(255, 42, 133, 0.4));
                transition: all 0.2s;
            }
            .btn-action-save:hover {
                box-shadow: 0 0 24px rgba(255, 42, 133, 0.7);
                transform: translateY(-1px);
            }
        </style>

        <div class="config-wrapper">
            <div class="config-header">
                <div>
                    <h1>// CONFIGURATION MATRIX</h1>
                    <div class="config-subtitle">CORE SYSTEM PARAMETERS &bull; CYBER DECK v<?= htmlspecialchars(\Indieinabox\Core\Version::VERSION) ?></div>
                </div>
                <a href="?action=logout" class="logout-btn">DISCONNECT</a>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <div class="alert-saved">
                    &check; Settings saved successfully! Site has been automatically rebuilt.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['rebuilt'])): ?>
                <div class="alert-saved">
                    &check; Site has been successfully rebuilt!
                </div>
            <?php endif; ?>

            <form action="" method="POST" id="configForm" enctype="multipart/form-data">
                <div class="config-tabs">
                    <button type="button" class="config-tab-btn active" onclick="showTab('tab-general')">
                        <span class="tab-num">01</span> General
                    </button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-kinds')">
                        <span class="tab-num">02</span> Content Kinds
                    </button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-localization')">
                        <span class="tab-num">03</span> Localization
                    </button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-social')">
                        <span class="tab-num">04</span> Social &amp; Federation
                    </button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-services')">
                        <span class="tab-num">05</span> Services &amp; Security
                    </button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-updates')">
                        <span class="tab-num">06</span> Updates
                    </button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-backups')">
                        <span class="tab-num">07</span> Backups
                    </button>
                </div>

                <!-- 01: GENERAL SETTINGS -->
                <div id="tab-general" class="tab-content active">
                    <fieldset>
                        <legend>Identity &amp; Domain</legend>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Site Name</label>
                                <input type="text" name="sitename" value="<?= htmlspecialchars($config['sitename'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Author Name</label>
                                <input type="text" name="author" value="<?= htmlspecialchars($config['author'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Site FQDN (URL)</label>
                                <input type="url" name="fqdn" value="<?= htmlspecialchars($config['fqdn'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Base Path</label>
                                <input type="text" name="base" value="<?= htmlspecialchars($config['base'] ?? '/') ?>">
                            </div>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Theme Settings</legend>
                        <div class="form-group">
                            <label>Active Theme</label>
                            <select name="active_theme">
                                <?php foreach ($availableThemes as $t): ?>
                                    <option value="<?= htmlspecialchars($t) ?>" <?= $activeTheme === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="grid-2">
                            <div class="form-group" style="border: 1px solid rgba(0, 240, 255, 0.2); padding: 1em; border-radius: 4px;">
                                <label>Install Official Theme</label>
                                <p style="font-size: 0.85em; color: var(--text-muted); margin-top: 0;">Select a theme to download and install.</p>
                                <select name="install_official_theme">
                                    <?php foreach ($officialThemes as $url => $name): ?>
                                        <option value="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="border: 1px solid rgba(0, 240, 255, 0.2); padding: 1em; border-radius: 4px;">
                                <label>Upload Custom Theme (.zip)</label>
                                <p style="font-size: 0.85em; color: var(--text-muted); margin-top: 0;">Upload your own theme zip file.</p>
                                <input type="file" name="custom_theme_zip" accept=".zip">
                            </div>
                        </div>
                        <small>Note: Installing or uploading a new theme will automatically extract it to your themes directory. You still need to set it as Active Theme above to use it.</small>
                    </fieldset>

                    <fieldset>
                        <legend>Build &amp; Content Options</legend>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Content Directory</label>
                                <input type="text" name="contentdir" value="<?= htmlspecialchars($config['contentdir'] ?? 'content') ?>">
                            </div>
                            <div class="form-group">
                                <label>Publish Directory</label>
                                <input type="text" name="outputdir" value="<?= htmlspecialchars($config['outputdir'] ?? 'public') ?>">
                            </div>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Default Category</label>
                                <input type="text" name="defaultcategory" value="<?= htmlspecialchars($config['defaultcategory'] ?? 'General') ?>">
                            </div>
                            <div class="form-group">
                                <label>HTML Postprocessing</label>
                                <select name="htmlpostprocessing">
                                    <option value="none" <?= ($config['htmlpostprocessing'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                                    <option value="minify" <?= ($config['htmlpostprocessing'] ?? 'minify') === 'minify' ? 'selected' : '' ?>>Minify</option>
                                    <option value="beautify" <?= ($config['htmlpostprocessing'] ?? '') === 'beautify' ? 'selected' : '' ?>>Beautify</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="feed_limit">RSS/Atom Feed Limit</label>
                            <input type="number" name="feed_limit" id="feed_limit" value="<?= (int)($config['feed_limit'] ?? 20) ?>" min="0">
                            <small>Number of latest posts to include in the RSS and Atom feeds. Set to 0 for unlimited.</small>
                        </div>
                        <div class="form-group">
                            <label>Supported File Extensions (comma separated)</label>
                            <input type="text" name="support" value="<?= htmlspecialchars(implode(', ', $config['support'] ?? ['md', 'txt', 'html', 'htm'])) ?>">
                        </div>
                        <div class="form-group" style="margin-top: 1rem;">
                            <div class="checkbox-group">
                                <input type="checkbox" name="prettylinks" id="prettylinks" <?= $prettyLinksActive ? 'checked' : '' ?>>
                                <label for="prettylinks">Pretty Links (folder/index.html format)</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" name="buildall" id="buildall" <?= ($config['buildall'] ?? true) ? 'checked' : '' ?>>
                                <label for="buildall">Build pages without frontmatter</label>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <!-- 02: CONTENT KINDS -->
                <div id="tab-kinds" class="tab-content">
                    <fieldset>
                        <legend>Configured Content Kinds</legend>
                        <?php foreach ($kinds as $k => $data): ?>
                            <div class="kind-card">
                                <h3>
                                    <span><?= htmlspecialchars($k) ?></span>
                                    <button type="submit" name="remove_kind" value="<?= htmlspecialchars($k) ?>" class="btn-secondary" style="color: #f43f5e; border-color: rgba(244, 63, 94, 0.5);">Remove</button>
                                </h3>
                                <div class="grid-2">
                                    <div class="form-group" style="display:none;">
                                        <label>Content Directory</label>
                                        <input type="hidden" name="kinds[<?= htmlspecialchars($k) ?>][content_dir]" value="<?= htmlspecialchars(is_array($data['content_dir'] ?? '') ? json_encode($data['content_dir'], JSON_UNESCAPED_UNICODE) : (is_string($data['content_dir'] ?? '') ? $data['content_dir'] : '')) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Display Mode</label>
                                        <select name="kinds[<?= htmlspecialchars($k) ?>][display_mode]">
                                            <option value="default" <?= ($data['display_mode'] ?? 'default') === 'default' ? 'selected' : '' ?>>Default</option>
                                            <option value="full_content" <?= ($data['display_mode'] ?? '') === 'full_content' ? 'selected' : '' ?>>Full Content</option>
                                            <option value="thumbnail_snippet" <?= ($data['display_mode'] ?? '') === 'thumbnail_snippet' ? 'selected' : '' ?>>Thumbnail Snippet</option>
                                        </select>
                                    </div>
                                    <div class="grid-2">
                                        <div class="form-group color-picker">
                                            <label>BG Color</label>
                                            <input type="color" name="kinds[<?= htmlspecialchars($k) ?>][palette][bg]" value="<?= htmlspecialchars($data['palette']['bg'] ?? '#ffffff') ?>">
                                        </div>
                                        <div class="form-group color-picker">
                                            <label>FG Color</label>
                                            <input type="color" name="kinds[<?= htmlspecialchars($k) ?>][palette][fg]" value="<?= htmlspecialchars($data['palette']['fg'] ?? '#000000') ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="checkbox-group" style="margin-top: 0.5rem;">
                                    <input type="checkbox" name="kinds[<?= htmlspecialchars($k) ?>][has_title]" id="kinds_<?= htmlspecialchars($k) ?>_ht" <?= !empty($data['has_title']) ? 'checked' : '' ?>>
                                    <label for="kinds_<?= htmlspecialchars($k) ?>_ht">Has Title</label>
                                    <input type="checkbox" name="kinds[<?= htmlspecialchars($k) ?>][show_on_home]" id="kinds_<?= htmlspecialchars($k) ?>_soh" <?= !empty($data['show_on_home']) ? 'checked' : '' ?>>
                                    <label for="kinds_<?= htmlspecialchars($k) ?>_soh">Show on Home</label>
                                    <input type="checkbox" name="kinds[<?= htmlspecialchars($k) ?>][show_in_menu]" id="kinds_<?= htmlspecialchars($k) ?>_sim" <?= (!isset($data['show_in_menu']) || !empty($data['show_in_menu'])) ? 'checked' : '' ?>>
                                    <label for="kinds_<?= htmlspecialchars($k) ?>_sim">Show in Menu</label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="kind-card" style="border: 2px dashed rgba(0, 240, 255, 0.4); background: rgba(0, 240, 255, 0.03);">
                            <h3 style="color: var(--neon-cyan);">+ Add New Content Kind</h3>
                            <div class="grid-2">
                                <div class="form-group">
                                    <label>Kind ID (e.g. video)</label>
                                    <input type="text" name="kinds[__new__][key]" placeholder="video">
                                </div>
                                <div class="form-group">
                                    <label>Display Mode</label>
                                    <select name="kinds[__new__][display_mode]">
                                        <option value="default">Default</option>
                                        <option value="full_content">Full Content</option>
                                        <option value="thumbnail_snippet">Thumbnail Snippet</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Translations</label>
                                <div class="grid-2">
                                    <?php foreach ($langArr as $l): ?>
                                    <div class="color-picker" style="margin-bottom: 5px; flex-direction: column; align-items: flex-start; background: rgba(0,0,0,0.3); padding: 8px; border-radius: 4px;">
                                        <strong><?= htmlspecialchars($l) ?></strong>
                                        <label style="font-size: 0.8rem; margin-top: 4px;">Title:</label>
                                        <input type="text" name="kinds[__new__][title][<?= htmlspecialchars($l) ?>]" style="margin-bottom: 5px;">
                                        <label style="font-size: 0.8rem;">Directory Path:</label>
                                        <input type="text" name="kinds[__new__][content_dir][<?= htmlspecialchars($l) ?>]" placeholder="e.g. videos">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="grid-2">
                                <div class="form-group color-picker">
                                    <label>BG Color</label>
                                    <input type="color" name="kinds[__new__][palette][bg]" value="#ffffff">
                                </div>
                                <div class="form-group color-picker">
                                    <label>FG Color</label>
                                    <input type="color" name="kinds[__new__][palette][fg]" value="#000000">
                                </div>
                            </div>
                            <div class="checkbox-group" style="margin-top: 0.5rem;">
                                <input type="checkbox" name="kinds[__new__][has_title]" id="kinds_new_ht">
                                <label for="kinds_new_ht">Has Title</label>
                                <input type="checkbox" name="kinds[__new__][show_on_home]" id="kinds_new_soh">
                                <label for="kinds_new_soh">Show on Home</label>
                                <input type="checkbox" name="kinds[__new__][show_in_menu]" id="kinds_new_sim" checked>
                                <label for="kinds_new_sim">Show in Menu</label>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <!-- 03: LOCALIZATION & TRANSLATIONS -->
                <div id="tab-localization" class="tab-content">
                    <fieldset>
                        <legend>Languages &amp; Primary Translation</legend>
                        <p style="font-size: 0.88em; color: var(--text-muted); margin-top: 2px; margin-bottom: 12px;">
                            The first language in the list is always the <strong>Main (Default)</strong> translation route.
                        </p>
                        <table class="tech-table">
                            <thead>
                                <tr>
                                    <th>Language Code</th>
                                    <th style="width: 140px;">Status</th>
                                    <th style="width: 90px; text-align: center;">Order</th>
                                    <th style="width: 190px; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($langArr as $idx => $l): ?>
                                <tr>
                                    <td style="font-weight: <?= $idx === 0 ? '700' : 'normal' ?>; font-family: 'JetBrains Mono', monospace;">
                                        <?= htmlspecialchars($l) ?>
                                        <input type="hidden" name="lang[]" value="<?= htmlspecialchars($l) ?>">
                                    </td>
                                    <td>
                                        <?php if ($idx === 0): ?>
                                            <span style="background: rgba(0, 255, 159, 0.15); color: var(--neon-green); border: 1px solid rgba(0, 255, 159, 0.4); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; font-family: 'JetBrains Mono', monospace;">Main (Default)</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.8rem; font-family: 'JetBrains Mono', monospace;">Sub-language</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: inline-flex; gap: 4px;">
                                            <button type="submit" name="move_up_lang" value="<?= htmlspecialchars($l) ?>" class="btn-secondary" style="padding: 2px 7px; font-size: 0.75rem;" <?= $idx === 0 ? 'disabled' : '' ?> title="Move Up">&blacktriangle;</button>
                                            <button type="submit" name="move_down_lang" value="<?= htmlspecialchars($l) ?>" class="btn-secondary" style="padding: 2px 7px; font-size: 0.75rem;" <?= $idx === count($langArr) - 1 ? 'disabled' : '' ?> title="Move Down">&blacktriangledown;</button>
                                        </div>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: inline-flex; gap: 6px; justify-content: flex-end;">
                                            <?php if ($idx > 0): ?>
                                                <button type="submit" name="set_main_lang" value="<?= htmlspecialchars($l) ?>" class="btn-secondary" style="padding: 4px 8px; font-size: 0.8rem;" title="Make this the main translation">Make Main</button>
                                            <?php endif; ?>
                                            <?php if (count($langArr) > 1): ?>
                                                <button type="submit" name="remove_lang" value="<?= htmlspecialchars($l) ?>" class="btn-secondary" style="padding: 4px 8px; font-size: 0.8rem; color: #f43f5e; border-color: rgba(244, 63, 94, 0.4);">Remove</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn-secondary" onclick="addLanguage()">+ Add Language</button>
                    </fieldset>

                    <fieldset>
                        <legend>Translation Parity &amp; Generation</legend>
                        <div class="form-group">
                            <label>Translation Parity Mode</label>
                            <select name="translation_parity">
                                <option value="full" <?= ($config['translation_parity'] ?? 'full') === 'full' ? 'selected' : '' ?>>Full (All directions)</option>
                                <option value="from-main-only" <?= ($config['translation_parity'] ?? '') === 'from-main-only' ? 'selected' : '' ?>>From Main Language Only</option>
                                <option value="from-sublang-only" <?= ($config['translation_parity'] ?? '') === 'from-sublang-only' ? 'selected' : '' ?>>From Sub-languages Only</option>
                                <option value="inter-sublang-only" <?= ($config['translation_parity'] ?? '') === 'inter-sublang-only' ? 'selected' : '' ?>>Inter Sub-languages Only</option>
                                <option value="disabled" <?= ($config['translation_parity'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                            </select>
                            <small>Controls which pages are required to have translations in other languages.</small>
                        </div>
                        <div class="form-group">
                            <label>Translation Auto-Generation</label>
                            <select name="translation_auto">
                                <option value="pseudo" <?= ($config['translation_auto'] ?? 'pseudo') === 'pseudo' ? 'selected' : '' ?>>Pseudo (Virtualizes translations with [LANG] prefix)</option>
                                <option value="disabled" <?= ($config['translation_auto'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled (Fails build if required parity is not met)</option>
                            </select>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Global UI Translations</legend>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding: 0.75rem 1rem; background: rgba(0, 240, 255, 0.05); border: 1px solid rgba(0, 240, 255, 0.2); border-radius: 4px;">
                            <span style="font-size: 0.85rem; color: var(--text-muted);">Auto-populate missing translations using bundled locale dictionaries (<code>pt</code>, <code>es</code>, <code>en</code>, etc.).</span>
                            <button type="submit" name="autofill_locale" value="all" class="btn-secondary" style="padding: 4px 10px; font-size: 0.8rem;">Auto-fill from Locales</button>
                        </div>
                        <?php foreach ($globalStrings as $origText => $desc): ?>
                            <div style="margin-bottom: 1.25rem; border-bottom: 1px dashed rgba(0, 240, 255, 0.15); padding-bottom: 1rem;">
                                <strong style="color: #ffffff;"><?= htmlspecialchars($origText) ?></strong> 
                                <span style="font-size: 0.85em; color: var(--text-muted);">(<?= htmlspecialchars($desc) ?>)</span>
                                <div class="grid-2" style="margin-top: 0.5rem;">
                                    <?php foreach ($langArr as $l): ?>
                                        <div class="color-picker" style="margin-bottom: 5px;">
                                            <span style="width: 50px; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: var(--neon-cyan);"><?= htmlspecialchars($l) ?></span>
                                            <input type="text" name="translations[<?= htmlspecialchars($origText) ?>][<?= htmlspecialchars($l) ?>]" value="<?= htmlspecialchars($config['translations'][$origText][$l] ?? '') ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>

                    <fieldset>
                        <legend>Content Kinds Translations</legend>
                        <?php foreach ($kinds as $k => $data): ?>
                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                <label style="color: var(--neon-cyan); font-size: 0.95rem;"><?= htmlspecialchars(ucfirst($k)) ?> Translations</label>
                                <div class="grid-2">
                                    <?php foreach ($langArr as $l): ?>
                                    <div class="color-picker" style="margin-bottom: 10px; flex-direction: column; align-items: flex-start; background: rgba(0,0,0,0.3); padding: 8px; border-radius: 4px;">
                                        <strong style="font-family: 'JetBrains Mono', monospace; color: var(--neon-pink);"><?= htmlspecialchars($l) ?></strong>
                                        <label style="font-size: 0.8rem; margin-top: 4px;">Title:</label>
                                        <input type="text" name="kinds[<?= htmlspecialchars($k) ?>][title][<?= htmlspecialchars($l) ?>]" value="<?= htmlspecialchars($data['title'][$l] ?? '') ?>">
                                        <label style="font-size: 0.8rem; margin-top: 4px;">Directory Path:</label>
                                        <input type="text" name="kinds[<?= htmlspecialchars($k) ?>][content_dir][<?= htmlspecialchars($l) ?>]" value="<?= htmlspecialchars(is_array($data['content_dir'] ?? null) ? ($data['content_dir'][$l] ?? '') : ($data['content_dir'] ?? '')) ?>">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                </div>

                <!-- 04: SOCIAL & FEDERATION -->
                <div id="tab-social" class="tab-content">
                    <fieldset>
                        <legend>TwTxt / Microblogging Settings</legend>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Twtxt Nickname</label>
                                <input type="text" name="twtxt_nick" value="<?= htmlspecialchars($config['twtxt']['nick'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Twtxt Avatar URL</label>
                                <input type="url" name="twtxt_avatar" value="<?= htmlspecialchars($config['twtxt']['avatar'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Twtxt Description</label>
                            <input type="text" name="twtxt_description" value="<?= htmlspecialchars($config['twtxt']['description'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Subscribed Feeds (Format: `nickname feed_url` one per line)</label>
                            <?php
                            $followLines = [];
                            foreach (($config['twtxt']['following'] ?? []) as $f) {
                                $followLines[] = "{$f['nick']} {$f['url']}";
                            }
                            ?>
                            <textarea name="twtxt_following" placeholder="bob https://bob.com/twtxt.txt"><?= htmlspecialchars(implode("\n", $followLines)) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Configured Hubs (one URL per line)</label>
                            <textarea name="twtxt_hubs" placeholder="https://hub.twtxt.org"><?= htmlspecialchars(implode("\n", $config['twtxt']['hubs'] ?? [])) ?></textarea>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Federation (ActivityPub)</legend>
                        <div class="checkbox-group" style="margin-bottom: 1em;">
                            <input type="checkbox" name="activitypub_enabled" id="activitypub_enabled" <?= !empty($config['activitypub_enabled']) ? 'checked' : '' ?>>
                            <label for="activitypub_enabled">Enable ActivityPub Federation (Mastodon, Misskey, etc.)</label>
                        </div>
                        <div class="form-group">
                            <label>Fediverse Handle (e.g. 'schwartz')</label>
                            <input type="text" name="activitypub_handle" value="<?= htmlspecialchars($config['activitypub_handle'] ?? 'schwartz') ?>">
                            <small>Your full handle will be <code>@your_handle@your_fqdn</code></small>
                        </div>
                        <div class="checkbox-group" style="margin-top: 1em;">
                            <input type="checkbox" name="activitypub_cache_remote_emojis" id="activitypub_cache_remote_emojis" <?= !empty($config['activitypub_cache_remote_emojis']) ? 'checked' : '' ?>>
                            <label for="activitypub_cache_remote_emojis">Cache remote custom emojis locally</label>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Microsub Feeds &amp; Media</legend>
                        <div class="checkbox-group">
                            <input type="checkbox" name="download_media_image" id="download_media_image" <?= ($config['download_media_image'] ?? true) ? 'checked' : '' ?>>
                            <label for="download_media_image">Download remote images locally</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="download_media_video" id="download_media_video" <?= ($config['download_media_video'] ?? true) ? 'checked' : '' ?>>
                            <label for="download_media_video">Download remote videos locally</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="download_media_audio" id="download_media_audio" <?= ($config['download_media_audio'] ?? true) ? 'checked' : '' ?>>
                            <label for="download_media_audio">Download remote audio locally</label>
                        </div>
                        <div class="form-group" style="margin-top: 1em;">
                            <label>Max Download Size (MB)</label>
                            <input type="number" name="download_media_max_size_mb" value="<?= htmlspecialchars((string)($config['download_media_max_size_mb'] ?? 10)) ?>" step="0.1" min="0">
                            <small>Files larger than this limit will not be downloaded. Set to 0 to disable size limit.</small>
                        </div>
                    </fieldset>
                </div>

                <!-- 05: SERVICES & SECURITY -->
                <div id="tab-services" class="tab-content">
                    <fieldset>
                        <legend>Shortlink Service</legend>
                        <div class="form-group">
                            <p style="margin-top: 0;"><small>If enabled, IndieInABox will attempt to automatically shorten your links using a remote service. If disabled, it will use a local short hash instead (e.g. <code>/s/a1b2c3d4</code>).</small></p>
                            <div class="checkbox-group">
                                <input type="checkbox" name="shortlink[enabled]" id="shortlink_enabled" value="1" <?= !empty($config['shortlink']['enabled']) ? 'checked' : '' ?>>
                                <label for="shortlink_enabled">Enable Remote Shortlinks (Nullpointer / Rustypaste compatible)</label>
                            </div>
                            
                            <div class="checkbox-group" style="margin-top: 1rem;">
                                <input type="checkbox" name="webarchive_enabled" id="webarchive_enabled" value="1" <?= !empty($config['webarchive_enabled']) ? 'checked' : '' ?>>
                                <label for="webarchive_enabled">Enable automatic WebArchive (archive.org) submissions</label>
                            </div>
                            
                            <div class="checkbox-group" style="margin-top: 0.5rem;">
                                <input type="checkbox" name="webmention_enabled" id="webmention_enabled" value="1" <?= !empty($config['webmention_enabled']) ? 'checked' : '' ?>>
                                <label for="webmention_enabled">Enable automatic outgoing Webmentions</label>
                            </div>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Server URL</label>
                                <input type="url" name="shortlink[server]" value="<?= htmlspecialchars($config['shortlink']['server'] ?? 'https://0x0.st') ?>" placeholder="https://0x0.st">
                            </div>
                            <div class="form-group">
                                <label>POST Parameter Name</label>
                                <input type="text" name="shortlink[parameter]" value="<?= htmlspecialchars($config['shortlink']['parameter'] ?? 'shorten') ?>" placeholder="shorten or url">
                            </div>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Auth Header Name (Optional)</label>
                                <input type="text" name="shortlink[auth_header]" value="<?= htmlspecialchars($config['shortlink']['auth_header'] ?? '') ?>" placeholder="e.g. Authorization">
                            </div>
                            <div class="form-group">
                                <label>Auth Token (Optional)</label>
                                <input type="text" name="shortlink[auth_token]" value="<?= htmlspecialchars($config['shortlink']['auth_token'] ?? '') ?>" placeholder="Token value">
                            </div>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Spam Protection (Akismet)</legend>
                        <div class="form-group">
                            <label>Akismet API Key (Leave blank to disable)</label>
                            <input type="text" name="akismet_api_key" value="<?= htmlspecialchars((string)($config['akismet_api_key'] ?? '')) ?>" placeholder="Enter API Key">
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Webhooks &amp; Automation</legend>
                        <p style="margin-top: 0;"><small>Secure external triggers for background cron processing and on-demand site rebuilds. Can also be defined via environment variables (<code>CRON_TOKEN</code>, <code>BUILD_TOKEN</code>, <code>WEBHOOK_TOKEN</code>).</small></p>
                        <div class="grid-2">
                            <div class="form-group">
                                <label for="cron_token">Cron Webhook Token (<code>/cron</code>)</label>
                                <input type="text" name="cron_token" id="cron_token" value="<?= htmlspecialchars((string)($config['cron_token'] ?? '')) ?>" placeholder="Leave blank for local-only, or enter secret token">
                                <p class="help">Required to trigger <code>GET /cron?token=...</code> externally.</p>
                            </div>
                            <div class="form-group">
                                <label for="build_token">Build Webhook Token (<code>/build</code>)</label>
                                <input type="text" name="build_token" id="build_token" value="<?= htmlspecialchars((string)($config['build_token'] ?? '')) ?>" placeholder="Leave blank for local-only, or enter secret token">
                                <p class="help">Required to trigger <code>POST /build?token=...</code> from Git/Sync watchers.</p>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset style="border-color: var(--neon-pink);">
                        <legend style="border-color: var(--neon-pink); color: var(--neon-pink);">Security</legend>
                        <div class="form-group">
                            <label>Change Admin Password (Optional)</label>
                            <input type="password" name="new_password" placeholder="Leave blank to keep current password">
                        </div>
                    </fieldset>
                </div>

                <!-- 06: UPDATES -->
                <div id="tab-updates" class="tab-content">
                    <fieldset>
                        <legend>Dogfooding &amp; Updates</legend>
                        <p style="font-size: 0.9em; margin-top: 0; color: var(--text-muted);">
                            Configure auto-updates or manually trigger updates. The list of versions is fetched asynchronously in the background.
                        </p>
                        <div class="grid-2">
                            <div class="form-group checkbox-group">
                                <label>
                                    <input type="hidden" name="auto_upgrade_stable" value="0">
                                    <input type="checkbox" name="auto_upgrade_stable" value="1" <?= !empty($config['auto_upgrade_stable']) ? 'checked' : '' ?>>
                                    Auto-upgrade Stable Releases
                                </label>
                            </div>
                            <div class="form-group checkbox-group">
                                <label>
                                    <input type="hidden" name="auto_upgrade_nightly" value="0">
                                    <input type="checkbox" name="auto_upgrade_nightly" value="1" <?= !empty($config['auto_upgrade_nightly']) ? 'checked' : '' ?>>
                                    Auto-upgrade Nightly Builds
                                </label>
                            </div>
                        </div>

                        <?php
                        $availableUpdates = \Indieinabox\Core\Database::getSetting('available_updates', []);
                        $lastCheck = \Indieinabox\Core\Database::getSetting('last_update_check', 0);
                        if (empty($availableUpdates)) {
                            echo '<p style="color: var(--text-muted); font-size: 0.9rem;">No updates available or checking hasn\'t run yet. (Last check: ' . ($lastCheck ? date('Y-m-d H:i:s', $lastCheck) : 'Never') . ')</p>';
                        } else {
                            echo '<h4 style="color: var(--neon-cyan); margin: 1.5rem 0 0.75rem;">Available Versions</h4><ul style="list-style: none; padding: 0;">';
                            foreach (array_slice($availableUpdates, 0, 5) as $update) {
                                $badge = $update['prerelease'] ? '<span style="background: var(--neon-amber); color: #000; padding: 2px 6px; border-radius: 4px; font-size: 0.75em; font-weight: 700;">Nightly</span>' : '<span style="background: var(--neon-green); color: #000; padding: 2px 6px; border-radius: 4px; font-size: 0.75em; font-weight: 700;">Stable</span>';
                                echo '<li style="margin-bottom: 12px; background: rgba(9, 13, 20, 0.7); border: 1px solid rgba(0, 240, 255, 0.2); border-radius: 4px; padding: 12px;">';
                                echo '<strong>' . htmlspecialchars($update['name']) . '</strong> ' . $badge;
                                echo '<br><small style="color: var(--text-muted);">Published: ' . htmlspecialchars($update['published_at']) . '</small>';
                                echo '<div style="margin-top: 8px;">';
                                echo '<button type="button" class="btn-secondary" onclick="submitActionForm(\'manual_update\', \'download_url\', \'' . htmlspecialchars($update['download_url']) . '\')">Update to this version</button>';
                                echo '</div>';
                                echo '</li>';
                            }
                            echo '</ul>';
                        }
                        ?>

                        <?php
                        $backups = \Indieinabox\Services\UpdateService::getLocalBackups();
                        if (!empty($backups)) {
                            echo '<h4 style="color: var(--neon-cyan); margin: 1.5rem 0 0.75rem;">Local Backups (Rollback)</h4><ul style="list-style: none; padding: 0;">';
                            foreach ($backups as $bkp) {
                                echo '<li style="margin-bottom: 10px; background: rgba(9, 13, 20, 0.7); border: 1px solid rgba(0, 240, 255, 0.2); border-radius: 4px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center;">';
                                echo '<div><strong style="font-family: \'JetBrains Mono\', monospace;">' . htmlspecialchars($bkp['filename']) . '</strong> <small style="color: var(--text-muted);">(' . date('Y-m-d H:i:s', $bkp['date']) . ', ' . (string) round($bkp['size'] / 1024, 2) . ' KB)</small></div>';
                                echo '<button type="button" class="btn-secondary" style="color: var(--neon-amber); border-color: rgba(255, 208, 0, 0.5);" onclick="submitActionForm(\'rollback_update\', \'backup_filename\', \'' . htmlspecialchars($bkp['filename']) . '\')">Rollback</button>';
                                echo '</li>';
                            }
                            echo '</ul>';
                        }
                        ?>
                    </fieldset>
                </div>

                <!-- 07: BACKUPS -->
                <div id="tab-backups" class="tab-content">
                    <fieldset>
                        <legend>Backup Configuration</legend>
                        <p style="margin-top:0; color: var(--text-muted); font-size: 0.9rem;">Configure storage locations and automated snapshot retention.</p>

                        <div class="form-group">
                            <label for="backup_dir">Backup Destination Directory</label>
                            <input type="text" name="backup_dir" id="backup_dir" value="<?= htmlspecialchars($config['backup_dir'] ?? '../backup') ?>">
                            <small>Absolute path or relative to the project root.</small>
                        </div>

                        <div class="form-group">
                            <label for="backup_limit">Keep Last N Backups</label>
                            <input type="number" name="backup_limit" id="backup_limit" value="<?= htmlspecialchars((string)($config['backup_limit'] ?? 5)) ?>" min="1">
                        </div>

                        <div class="checkbox-group">
                            <input type="hidden" name="backup_cron_enabled" value="0">
                            <input type="checkbox" name="backup_cron_enabled" id="backup_cron_enabled" value="1" <?= !empty($config['backup_cron_enabled']) ? 'checked' : '' ?>>
                            <label for="backup_cron_enabled">Enable Automatic Daily Backups (via Cron)</label>
                        </div>
                        
                        <div class="checkbox-group" style="margin-top: 10px;">
                            <input type="hidden" name="backup_skip_content" value="0">
                            <input type="checkbox" name="backup_skip_content" id="backup_skip_content" value="1" <?= !empty($config['backup_skip_content']) ? 'checked' : '' ?>>
                            <label for="backup_skip_content">Skip Content Directory (`--no-content`)</label>
                        </div>

                        <div class="checkbox-group" style="margin-top: 10px;">
                            <input type="hidden" name="backup_skip_media" value="0">
                            <input type="checkbox" name="backup_skip_media" id="backup_skip_media" value="1" <?= !empty($config['backup_skip_media']) ? 'checked' : '' ?>>
                            <label for="backup_skip_media">Skip Media Directory (`--no-media`)</label>
                        </div>
                    </fieldset>
                </div>

                <!-- STICKY ACTION BAR -->
                <div class="submit-group">
                    <button type="submit" name="action" value="rebuild_site" class="btn-action-rebuild">Rebuild Only</button>
                    <button type="submit" class="btn-action-save">Save Settings &amp; Rebuild</button>
                </div>
            </form>

            <script>
                function showTab(tabId) {
                    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
                    document.querySelectorAll('.config-tab-btn').forEach(el => el.classList.remove('active'));
                    
                    const target = document.getElementById(tabId);
                    if (target) {
                        target.classList.add('active');
                    }
                    const btn = document.querySelector(`.config-tab-btn[onclick*="${tabId}"]`);
                    if (btn) {
                        btn.classList.add('active');
                    }
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', '#' + tabId);
                    }
                }

                document.addEventListener('DOMContentLoaded', function() {
                    const hash = window.location.hash ? window.location.hash.substring(1) : '';
                    if (hash && document.getElementById(hash)) {
                        showTab(hash);
                    }
                });

                function addLanguage() {
                    let langCode = prompt("Enter the new language code (e.g. fr, de):");
                    if (langCode) {
                        langCode = langCode.trim();
                        if (langCode.length > 0) {
                            let form = document.getElementById('configForm');
                            let input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'lang[]';
                            input.value = langCode;
                            form.appendChild(input);
                            form.submit();
                        }
                    }
                }

                function submitActionForm(action, paramName, paramValue) {
                    let form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    
                    let actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = action;
                    form.appendChild(actionInput);
                    
                    if (paramName) {
                        let paramInput = document.createElement('input');
                        paramInput.type = 'hidden';
                        paramInput.name = paramName;
                        paramInput.value = paramValue;
                        form.appendChild(paramInput);
                    }
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            </script>
        </div>
        <?php
        $inner = (string) ob_get_clean();
        return AdminLayoutView::render($inner, 'config', $fqdn);
    }
}
