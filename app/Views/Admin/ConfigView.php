<?php

declare(strict_types=1);

namespace Indieinabox\Views\Admin;

use Indieinabox\Site;

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
            <style>
                :root {
                    --bg: #F4F1EA;
                    --fg: #2C2E2F;
                    --accent: #ef4444;
                }
                body {
                    background-color: var(--bg);
                    color: var(--fg);
                    font-family: ui-monospace, SFMono-Regular, SF Mono, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                    line-height: 1.6;
                    max-width: 650px;
                    margin: 40px auto;
                    padding: 0 16px;
                }
                h1 {
                    color: var(--accent);
                }
                .error-message {
                    color: var(--accent);
                    margin-bottom: 1em;
                    font-weight: bold;
                }
                .form-group {
                    margin-bottom: 1.5em;
                }
                label {
                    display: block;
                    font-weight: bold;
                    margin-bottom: 0.5em;
                }
                input {
                    background: rgba(0, 0, 0, 0.05);
                    border: 1px solid var(--fg);
                    color: var(--fg);
                    padding: 8px 12px;
                    font-family: inherit;
                    width: 100%;
                    box-sizing: border-box;
                }
                button {
                    background: var(--fg);
                    color: var(--bg);
                    border: none;
                    padding: 10px 16px;
                    font-family: inherit;
                    cursor: pointer;
                    font-weight: bold;
                }
                button:hover {
                    background: var(--accent);
                }
            </style>
        </head>
        <body>
            <h1>Setup Setup Setup!</h1>
            <p>Indieinabox is not configured yet. Choose your password and site identity to get started.</p>

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
        $langStr = implode(', ', $langArr);
        $prettyLinksActive = $config['prettylinks'] ?? true;
        $fqdn = rtrim($site->metadata->fqdn ?? '', '/');

        ob_start();
        ?>
<div class="config-wrapper">
            <div class="nav-header">
                <h1>Configuration Panel</h1>
                <a href="?action=logout" class="logout-btn">Log Out</a>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <div class="alert-saved">
                    Settings saved successfully! Site has been automatically rebuilt.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['rebuilt'])): ?>
                <div class="alert-saved">
                    Site has been successfully rebuilt!
                </div>
            <?php endif; ?>

            <form action="" method="POST" id="configForm" enctype="multipart/form-data">
                <div class="config-tabs">
                    <button type="button" class="config-tab-btn active" onclick="showTab('tab-general')">General</button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-localization')">Localization</button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-kinds')">Content Kinds</button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-social')">Social & Federation</button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-services')">Services & Security</button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-updates')">Updates</button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-backups')">Backups</button>
                </div>


                <div id="tab-backups" class="tab-content">
                    <fieldset>
                        <legend>Backup Configuration</legend>
                        <p style="margin-top:0;">Configure where and how backups are stored.</p>

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

                <div id="tab-updates" class="tab-content">
                    <fieldset>
                        <legend>Dogfooding & Updates</legend>
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
                            echo '<p>No updates available or checking hasn\'t run yet. (Last check: ' . ($lastCheck ? date('Y-m-d H:i:s', $lastCheck) : 'Never') . ')</p>';
                        } else {
                            echo '<h4>Available Versions</h4><ul>';
                            foreach (array_slice($availableUpdates, 0, 5) as $update) {
                                $badge = $update['prerelease'] ? '<span style="background: #eab308; color: #000; padding: 2px 6px; border-radius: 4px; font-size: 0.8em;">Nightly</span>' : '<span style="background: #22c55e; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.8em;">Stable</span>';
                                echo '<li style="margin-bottom: 10px;">';
                                echo '<strong>' . htmlspecialchars($update['name']) . '</strong> ' . $badge;
                                echo '<br><small>Published: ' . htmlspecialchars($update['published_at']) . '</small>';
                                echo '<div style="margin-top: 5px; display: inline-block;">';
                                echo '<button type="button" class="btn" style="padding: 4px 10px; font-size: 0.9em;" onclick="submitActionForm(\'manual_update\', \'download_url\', \'' . htmlspecialchars($update['download_url']) . '\')">Update to this version</button>';
                                echo '</div>';
                                echo '</li>';
                            }
                            echo '</ul>';
                        }
                        ?>

                        <?php
                        $backups = \Indieinabox\Services\UpdateService::getLocalBackups();
                        if (!empty($backups)) {
                            echo '<h4>Local Backups (Rollback)</h4><ul>';
                            foreach ($backups as $bkp) {
                                echo '<li style="margin-bottom: 10px;">';
                                echo htmlspecialchars($bkp['filename']) . ' <small>(' . date('Y-m-d H:i:s', $bkp['date']) . ', ' . round($bkp['size'] / 1024, 2) . ' KB)</small>';
                                echo '<div style="margin-top: 5px; display: inline-block; margin-left: 10px;">';
                                echo '<button type="button" class="btn" style="padding: 4px 10px; font-size: 0.9em; background: var(--accent); color: white; border: none;" onclick="submitActionForm(\'rollback_update\', \'backup_filename\', \'' . htmlspecialchars($bkp['filename']) . '\')">Rollback</button>';
                                echo '</div>';
                                echo '</li>';
                            }
                            echo '</ul>';
                        }
                        ?>
                    </fieldset>
                </div>

                <div id="tab-general" class="tab-content active">
                <fieldset>
                    <legend>General Settings</legend>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Site Name</label>
                            <input type="text" name="sitename" value="<?= htmlspecialchars($config['sitename'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Author Name</label>
                            <input type="text" name="author" value="<?= htmlspecialchars($config['author'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Site FQDN (URL)</label>
                            <input type="url" name="fqdn" value="<?= htmlspecialchars($config['fqdn'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Base Path</label>
                        <input type="text" name="base" value="<?= htmlspecialchars($config['base'] ?? '/') ?>">
                    </div>
                </fieldset>

                <?php
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
                ?>
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
                        <div class="form-group" style="border: 1px solid var(--border-color); padding: 1em;">
                            <label>Install Official Theme</label>
                            <p style="font-size: 0.9em; color: #666; margin-top: 0;">Select a theme to download and install.</p>
                            <select name="install_official_theme">
                                <?php foreach ($officialThemes as $url => $name): ?>
                                    <option value="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="border: 1px solid var(--border-color); padding: 1em;">
                            <label>Upload Custom Theme (.zip)</label>
                            <p style="font-size: 0.9em; color: #666; margin-top: 0;">Upload your own theme zip file.</p>
                            <input type="file" name="custom_theme_zip" accept=".zip">
                        </div>
                    </div>
                    <small>Note: Installing or uploading a new theme will automatically extract it to your themes directory. You still need to set it as Active Theme above to use it.</small>
                </fieldset>

                <fieldset>
                    <legend>Build Options</legend>
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
                    <div class="form-group">
                        <label>Languages</label>
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1em; border: 1px solid var(--border-color);">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--fg); text-align: left; background: rgba(0,0,0,0.05);">
                                    <th style="padding: 8px;">Language Code</th>
                                    <th style="padding: 8px; width: 100px; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($langArr as $l): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 8px;">
                                        <?= htmlspecialchars($l) ?>
                                        <input type="hidden" name="lang[]" value="<?= htmlspecialchars($l) ?>">
                                    </td>
                                    <td style="padding: 8px; text-align: right;">
                                        <button type="submit" name="remove_lang" value="<?= htmlspecialchars($l) ?>" class="btn-secondary" style="margin: 0; padding: 4px 8px; font-size: 0.8rem;">Remove</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn-secondary" onclick="addLanguage()">Add Language</button>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="prettylinks" id="prettylinks" <?= $prettyLinksActive ? 'checked' : '' ?>>
                            <label for="prettylinks">Pretty Links (folder/index.html format)</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="buildall" id="buildall" <?= ($config['buildall'] ?? true) ? 'checked' : '' ?>>
                            <label for="buildall">Build pages without frontmatter</label>
                        </div>
                        <div class="checkbox-group">

                        </div>
                    </div>
                </fieldset>
                </div>

                <div id="tab-localization" class="tab-content">
                <fieldset>
                    <legend>Translations & Parity</legend>
                    <div class="form-group">
                        <label>Translation Parity Mode</label>
                        <select name="translation_parity">
                            <option value="full" <?= ($config['translation_parity'] ?? 'full') === 'full' ? 'selected' : '' ?>>Full (All directions)</option>
                            <option value="from-main-only" <?= ($config['translation_parity'] ?? '') === 'from-main-only' ? 'selected' : '' ?>>From Main Language Only</option>
                            <option value="from-sublang-only" <?= ($config['translation_parity'] ?? '') === 'from-sublang-only' ? 'selected' : '' ?>>From Sub-languages Only</option>
                            <option value="inter-sublang-only" <?= ($config['translation_parity'] ?? '') === 'inter-sublang-only' ? 'selected' : '' ?>>Inter Sub-languages Only</option>
                            <option value="disabled" <?= ($config['translation_parity'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                        </select>
                        <p style="font-size: 0.85em; opacity: 0.8; margin-top: 5px;">Controls which pages are required to have translations in other languages.</p>
                    </div>
                    <div class="form-group">
                        <label>Translation Auto-Generation</label>
                        <select name="translation_auto">
                            <option value="pseudo" <?= ($config['translation_auto'] ?? 'pseudo') === 'pseudo' ? 'selected' : '' ?>>Pseudo (Virtualizes translations with [LANG] prefix)</option>
                            <option value="disabled" <?= ($config['translation_auto'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled (Fails build if required parity is not met)</option>
                        </select>
                    </div>
                </fieldset>
                </div>

                <div id="tab-kinds" class="tab-content">
                <fieldset>
                    <legend>Content Kinds</legend>
                    <?php
                    $kinds = $config['kinds'] ?? [];
                    foreach ($kinds as $k => $data) {
                        ?>
                        <div class="kind-card">
                            <h3>
                                <?= htmlspecialchars($k) ?>
                                <button type="submit" name="remove_kind" value="<?= htmlspecialchars($k) ?>" class="btn-secondary" style="margin: 0; padding: 4px 8px; font-size: 0.8rem;">Remove</button>
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

                            <div class="checkbox-group">
                                <input type="checkbox" name="kinds[<?= htmlspecialchars($k) ?>][has_title]" id="kinds_<?= htmlspecialchars($k) ?>_ht" <?= !empty($data['has_title']) ? 'checked' : '' ?>>
                                <label for="kinds_<?= htmlspecialchars($k) ?>_ht">Has Title</label>
                                <input type="checkbox" name="kinds[<?= htmlspecialchars($k) ?>][show_on_home]" id="kinds_<?= htmlspecialchars($k) ?>_soh" <?= !empty($data['show_on_home']) ? 'checked' : '' ?>>
                                <label for="kinds_<?= htmlspecialchars($k) ?>_soh">Show on Home</label>
                                <input type="checkbox" name="kinds[<?= htmlspecialchars($k) ?>][show_in_menu]" id="kinds_<?= htmlspecialchars($k) ?>_sim" <?= (!isset($data['show_in_menu']) || !empty($data['show_in_menu'])) ? 'checked' : '' ?>>
                                <label for="kinds_<?= htmlspecialchars($k) ?>_sim">Show in Menu</label>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                    
                    <div class="kind-card" style="border: 2px dashed var(--border-color);">
                        <h3>➕ Add New Kind</h3>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Kind ID (e.g. video)</label>
                                <input type="text" name="kinds[__new__][key]" placeholder="video">
                            </div>
                            <div class="form-group" style="display:none;">
                                <label>Content Directory</label>
                                <input type="hidden" name="kinds[__new__][content_dir_legacy]" placeholder="videos">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Display Mode</label>
                            <select name="kinds[__new__][display_mode]">
                                <option value="default">Default</option>
                                <option value="full_content">Full Content</option>
                                <option value="thumbnail_snippet">Thumbnail Snippet</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Translations</label>
                            <div class="grid-2">
                                <?php foreach ($langArr as $l): ?>
                                <div class="color-picker" style="margin-bottom: 5px; flex-direction: column; align-items: flex-start;">
                                    <strong><?= htmlspecialchars($l) ?></strong>
                                    <label style="font-size: 0.85rem;">Title:</label>
                                    <input type="text" name="kinds[__new__][title][<?= htmlspecialchars($l) ?>]" style="margin-bottom: 5px;">
                                    <label style="font-size: 0.85rem;">Directory Path:</label>
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
                        <div class="checkbox-group">
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

                <div id="tab-social" class="tab-content">
                <fieldset>
                    <legend>TwTxt / Social Settings</legend>
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
                    <legend>Microsub Feeds & Media</legend>
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

                <div id="tab-localization-global" class="tab-content">
                <fieldset>
                    <legend>Global Translations</legend>
                    <?php
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
                    foreach ($globalStrings as $origText => $desc):
                    ?>
                        <div style="margin-bottom: 1.5em; border-bottom: 1px dashed var(--border-color); padding-bottom: 1em;">
                            <strong><?= htmlspecialchars($origText) ?></strong> <span style="font-size: 0.9em; opacity: 0.7;">(<?= htmlspecialchars($desc) ?>)</span>
                            <div class="grid-2" style="margin-top: 0.5em;">
                                <?php foreach ($langArr as $l): ?>
                                    <div class="color-picker" style="margin-bottom: 5px;">
                                        <span style="width: 50px;"><?= htmlspecialchars($l) ?></span>
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
                            <label><?= htmlspecialchars(ucfirst($k)) ?> Translations</label>
                            <div class="grid-2">
                                <?php foreach ($langArr as $l): ?>
                                <div class="color-picker" style="margin-bottom: 10px; flex-direction: column; align-items: flex-start;">
                                    <strong><?= htmlspecialchars($l) ?></strong>
                                    <label style="font-size: 0.85rem; margin-top: 5px;">Title:</label>
                                    <input type="text" name="kinds[<?= htmlspecialchars($k) ?>][title][<?= htmlspecialchars($l) ?>]" value="<?= htmlspecialchars($data['title'][$l] ?? '') ?>">
                                    <label style="font-size: 0.85rem; margin-top: 5px;">Directory Path:</label>
                                    <input type="text" name="kinds[<?= htmlspecialchars($k) ?>][content_dir][<?= htmlspecialchars($l) ?>]" value="<?= htmlspecialchars(is_array($data['content_dir'] ?? null) ? ($data['content_dir'][$l] ?? '') : ($data['content_dir'] ?? '')) ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
                </div>

                <div id="tab-services" class="tab-content">
                <fieldset>
                    <legend>Shortlink Service</legend>
                    <div class="form-group">
                        <p style="margin-top: 0;"><small>If enabled, IndieInABox will attempt to automatically shorten your links using a remote service. If disabled, it will use a local short hash instead (e.g. <code>/s/a1b2c3d4</code>).</small></p>
                        <input type="checkbox" name="shortlink[enabled]" id="shortlink_enabled" value="1" <?= !empty($config['shortlink']['enabled']) ? 'checked' : '' ?>>
                        <label for="shortlink_enabled">Enable Remote Shortlinks (Nullpointer / Rustypaste compatible)</label>
                        <p class="help">If disabled, shortlinks will be generated locally (e.g. /s/abc12345).</p>
                        
                        <div style="margin-top: 1rem;">
                            <input type="checkbox" name="webarchive_enabled" id="webarchive_enabled" value="1" <?= !empty($config['webarchive_enabled']) ? 'checked' : '' ?>>
                            <label for="webarchive_enabled">Enable automatic WebArchive (archive.org) submissions</label>
                            <p class="help">If enabled, all external links in your posts will be automatically submitted to the Wayback Machine.</p>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <input type="checkbox" name="webmention_enabled" id="webmention_enabled" value="1" <?= !empty($config['webmention_enabled']) ? 'checked' : '' ?>>
                            <label for="webmention_enabled">Enable automatic outgoing Webmentions</label>
                            <p class="help">If enabled, IndieInABox will attempt to notify other sites when you link to them.</p>
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

                <fieldset style="border-color: var(--accent);">
                    <legend>Security</legend>
                    <div class="form-group">
                        <label>Change Admin Password (Optional)</label>
                        <input type="password" name="new_password" placeholder="Leave blank to keep current password">
                    </div>
                </fieldset>
                </div>

                <div class="submit-group" style="position: sticky; bottom: 0; background: var(--glass-bg, #111827); padding: 15px; border-top: 1px solid var(--border-color); z-index: 100; margin-top: 2rem; border-radius: 8px; display: flex; gap: 10px;">
                    <button type="submit" name="action" value="rebuild_site" class="btn" style="flex: 1; font-size: 1.2rem; padding: 15px; background: transparent; border: 1px solid var(--border-color); color: var(--fg);">Rebuild Only</button>
                    <button type="submit" class="save-btn btn" style="flex: 2; font-size: 1.2rem; padding: 15px;">Save Settings & Rebuild</button>
                </div>
            </form>

            <script>
                function showTab(tabId) {
                    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
                    document.querySelectorAll('.config-tab-btn').forEach(el => el.classList.remove('active'));
                    
                    if (tabId === 'tab-localization') {
                        document.getElementById('tab-localization').classList.add('active');
                        document.getElementById('tab-localization-global').classList.add('active');
                    } else {
                        document.getElementById(tabId).classList.add('active');
                    }
                    
                    event.currentTarget.classList.add('active');
                }

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
