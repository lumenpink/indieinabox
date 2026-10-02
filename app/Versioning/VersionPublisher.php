<?php

declare(strict_types=1);

namespace Indieinabox\Versioning;

use Indieinabox\Markdown\ASTParser;
use Indieinabox\Markdown\GemtextRenderer;
use Indieinabox\Markdown\GophermapRenderer;
use Indieinabox\Markdown\HtmlRenderer;
use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\SiteBuilder;
use Indieinabox\Theme\ThemeManager;

/**
 * Class VersionPublisher
 *
 * Generates 100% static artifacts for versioned pages across HTML, Gemini, and Gopher.
 * Publishes:
 * - /{slug}/history/index.html: MediaWiki-style revision list and interactive static diff viewer
 * - /{slug}/v/{version}/index.html: Rendered historical snapshots with old-version warning
 * - Gemini & Gopher counterparts in their respective separate output directories.
 */
class VersionPublisher
{
    /**
     * Publishes all static versioning artifacts for a page.
     *
     * @param Page $page
     * @param Site $site
     * @param Pages $pages
     * @param array<string, mixed> $history
     * @return void
     */
    public static function publishAll(Page $page, Site $site, Pages $pages, array $history): void
    {
        self::publishHtmlHistory($page, $site, $pages, $history);
        self::publishHtmlSnapshots($page, $site, $pages, $history);
        self::publishGeminiHistory($page, $site, $history);
        self::publishGeminiSnapshots($page, $site, $history);
        self::publishGopherHistory($page, $site, $history);
        self::publishGopherSnapshots($page, $site, $history);
    }

    /**
     * Publishes the /{slug}/history/index.html page with MediaWiki layout.
     *
     * @param Page $page
     * @param Site $site
     * @param Pages $pages
     * @param array<string, mixed> $history
     * @return void
     */
    public static function publishHtmlHistory(Page $page, Site $site, Pages $pages, array $history): void
    {
        $base = $site->paths->baseDir;
        $slugPath = trim(str_replace('/', DIRECTORY_SEPARATOR, $page->slug), DIRECTORY_SEPARATOR);

        $outDir = $base . DIRECTORY_SEPARATOR . $site->paths->outputDirHtml;
        $historyDir = $outDir . DIRECTORY_SEPARATOR . $slugPath . DIRECTORY_SEPARATOR . 'history';
        if (!is_dir($historyDir)) {
            @mkdir($historyDir, 0777, true);
        }
        $destFile = $historyDir . DIRECTORY_SEPARATOR . 'index.html';

        $versions = $history['versions'] ?? [];
        $totalVersions = count($versions);
        $title = $page->title;

        // Build revision table rows
        $rowsHtml = '';
        $versionsReversed = array_reverse($versions);

        foreach ($versionsReversed as $idx => $v) {
            $versionTag = htmlspecialchars($v['version']);
            $date = htmlspecialchars($v['date']);
            $note = htmlspecialchars($v['note'] ?? '');
            $bytes = (int) ($v['bytes'] ?? 0);
            $bytesDiff = (int) ($v['bytes_diff'] ?? 0);

            $diffClass = 'bytes-neutral';
            $diffSign = '';
            if ($bytesDiff > 0) {
                $diffClass = 'bytes-positive';
                $diffSign = '+';
            } elseif ($bytesDiff < 0) {
                $diffClass = 'bytes-negative';
            }
            $diffBadge = sprintf(
                '<span class="%s">(%s%d bytes)</span>',
                $diffClass,
                $diffSign,
                $bytesDiff
            );

            // Radio buttons for diff comparison
            // By default: col 1 (old) selected on second item, col 2 (new) selected on first item
            $checkedOld = ($idx === 1 || ($totalVersions === 1 && $idx === 0)) ? 'checked' : '';
            $checkedNew = ($idx === 0) ? 'checked' : '';

            $snapshotUrl = '../v/' . rawurlencode($v['version']) . '/';

            $rowsHtml .= '<li class="wiki-history-item" data-version="' . $versionTag . '">' . "\n";
            $rowsHtml .= '  <span class="mw-history-radios">' . "\n";
            $rowsHtml .= '    <input type="radio" name="oldid" value="' . $versionTag . '" ' . $checkedOld . ' title="Versão mais antiga">' . "\n";
            $rowsHtml .= '    <input type="radio" name="diff" value="' . $versionTag . '" ' . $checkedNew . ' title="Versão mais recente">' . "\n";
            $rowsHtml .= '  </span>' . "\n";
            $rowsHtml .= '  <a href="' . $snapshotUrl . '" class="mw-changeslist-date"><strong>' . $versionTag . '</strong></a> · <time datetime="' . $date . '">' . $date . '</time>' . "\n";
            $rowsHtml .= '  <span class="mw-changeslist-separator">·</span>' . "\n";
            $rowsHtml .= '  <span class="mw-changeslist-size">' . $bytes . ' bytes</span> ' . $diffBadge . "\n";
            if (!empty($note)) {
                $rowsHtml .= '  <span class="comment">(' . $note . ')</span>' . "\n";
            }
            $rowsHtml .= '</li>' . "\n";
        }

        // Build default static pre-rendered diff (between latest and previous)
        $latest = $versions[$totalVersions - 1] ?? null;
        $prev = $totalVersions > 1 ? $versions[$totalVersions - 2] : null;

        $preDiffHtml = '';
        if ($latest !== null && $prev !== null) {
            $preDiffHtml = Differ::mediawikiDiffHtml(
                (string) ($prev['content'] ?? ''),
                (string) ($latest['content'] ?? ''),
                (string) $prev['version'],
                (string) $latest['version']
            );
        } elseif ($latest !== null) {
            $preDiffHtml = '<div class="notice-info"><p>Esta é a primeira versão registrada deste documento. Não há revisões anteriores para comparação.</p></div>';
        }

        // Prepare JSON payload of versions for instant 100% static client-side diffing
        $clientVersions = [];
        foreach ($versions as $v) {
            $clientVersions[$v['version']] = [
                'version' => $v['version'],
                'date' => $v['date'],
                'note' => $v['note'],
                'content' => $v['content'],
            ];
        }
        $clientJson = json_encode($clientVersions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        // Vanilla JS for 100% client-side offline diffing
        $clientJs = <<<JAVASCRIPT
<script>
(function() {
    const versions = {$clientJson};

    function splitLines(text) {
        return (text || '').replace(/\\r\\n|\\r/g, '\\n').split('\\n');
    }

    function lcsDiffOps(a, b) {
        const m = a.length, n = b.length;
        const lcs = Array.from({ length: m + 1 }, () => new Uint32Array(n + 1));
        for (let i = 0; i < m; i++) {
            for (let j = 0; j < n; j++) {
                if (a[i] === b[j]) {
                    lcs[i + 1][j + 1] = lcs[i][j] + 1;
                } else {
                    lcs[i + 1][j + 1] = Math.max(lcs[i + 1][j], lcs[i][j + 1]);
                }
            }
        }
        const ops = [];
        let i = m, j = n;
        while (i > 0 || j > 0) {
            if (i > 0 && j > 0 && a[i - 1] === b[j - 1]) {
                ops.push({ type: 'equal', line: a[i - 1] });
                i--; j--;
            } else if (j > 0 && (i === 0 || lcs[i][j - 1] >= lcs[i - 1][j])) {
                ops.push({ type: 'add', line: b[j - 1] });
                j--;
            } else if (i > 0 && (j === 0 || lcs[i][j - 1] < lcs[i - 1][j])) {
                ops.push({ type: 'del', line: a[i - 1] });
                i--;
            }
        }
        return ops.reverse();
    }

    function escapeHtml(str) {
        return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderDiff(vOldKey, vNewKey) {
        const vOld = versions[vOldKey];
        const vNew = versions[vNewKey];
        const container = document.getElementById('diff-output');
        if (!vOld || !vNew || !container) return;

        const oldLines = splitLines(vOld.content);
        const newLines = splitLines(vNew.content);
        const ops = lcsDiffOps(oldLines, newLines);

        let rowsHtml = '';
        let i = 0;
        const count = ops.length;

        while (i < count) {
            const op = ops[i];
            if (op.type === 'equal') {
                const esc = escapeHtml(op.line);
                rowsHtml += '<tr class="diff-row-context">';
                rowsHtml += '<td class="diff-marker"> </td><td class="diff-context"><code>' + esc + '</code></td>';
                rowsHtml += '<td class="diff-marker"> </td><td class="diff-context"><code>' + esc + '</code></td>';
                rowsHtml += '</tr>\\n';
                i++;
            } else {
                const dels = [], adds = [];
                while (i < count && ops[i].type !== 'equal') {
                    if (ops[i].type === 'del') dels.push(ops[i].line);
                    else if (ops[i].type === 'add') adds.push(ops[i].line);
                    i++;
                }
                const maxLen = Math.max(dels.length, adds.length);
                for (let k = 0; k < maxLen; k++) {
                    rowsHtml += '<tr class="diff-row-change">';
                    if (k < dels.length) {
                        rowsHtml += '<td class="diff-marker diff-marker-del">−</td>';
                        rowsHtml += '<td class="diff-deletedline"><del>' + escapeHtml(dels[k]) + '</del></td>';
                    } else {
                        rowsHtml += '<td class="diff-marker"> </td><td class="diff-empty"></td>';
                    }
                    if (k < adds.length) {
                        rowsHtml += '<td class="diff-marker diff-marker-add">+</td>';
                        rowsHtml += '<td class="diff-addedline"><ins>' + escapeHtml(adds[k]) + '</ins></td>';
                    } else {
                        rowsHtml += '<td class="diff-marker"> </td><td class="diff-empty"></td>';
                    }
                    rowsHtml += '</tr>\\n';
                }
            }
        }

        container.innerHTML = '<div class="diff-container table-responsive">' +
            '<table class="diff wiki-diff" role="grid">' +
            '<colgroup><col style="width: 2%;"><col style="width: 48%;"><col style="width: 2%;"><col style="width: 48%;"></colgroup>' +
            '<thead><tr>' +
            '<th colspan="2" class="diff-otitle"><strong>' + escapeHtml(vOld.version) + ' (' + escapeHtml(vOld.date) + ')</strong></th>' +
            '<th colspan="2" class="diff-ntitle"><strong>' + escapeHtml(vNew.version) + ' (' + escapeHtml(vNew.date) + ')</strong></th>' +
            '</tr></thead>' +
            '<tbody>' + rowsHtml + '</tbody>' +
            '</table></div>';
    }

    document.addEventListener('DOMContentLoaded', function() {
        const compareBtn = document.getElementById('mw-compare-btn');
        if (compareBtn) {
            compareBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const oldRadio = document.querySelector('input[name="oldid"]:checked');
                const newRadio = document.querySelector('input[name="diff"]:checked');
                if (oldRadio && newRadio) {
                    renderDiff(oldRadio.value, newRadio.value);
                }
            });
        }
    });
})();
</script>
JAVASCRIPT;

        // Construct HTML content inside full theme layout
        $historyPage = clone $page;
        $historyPage->title = 'Histórico de revisões: ' . $title;
        $historyPage->relpath .= '../';
        $historyPage->slug .= '/history';

        $bodyContent = <<<HTML
<div class="wiki-history-wrapper">
    <nav class="history-breadcrumbs" style="margin-bottom: 1em;">
        <a href="../">← Voltar para <strong>{$title}</strong></a>
    </nav>

    <h2>📜 Histórico de revisões: {$title}</h2>
    <p class="history-intro">Lista completa de versões e alterações registradas em tempo de build.</p>

    <form id="mw-history-compare-form" class="mw-history-form" style="margin-bottom: 2em;">
        <div class="mw-history-actions" style="margin-bottom: 1em;">
            <button type="button" id="mw-compare-btn" class="button btn-compare">🔍 Comparar revisões selecionadas</button>
        </div>

        <ul class="wiki-history-list" style="list-style: none; padding-left: 0;">
            {$rowsHtml}
        </ul>
    </form>

    <hr style="margin: 2em 0; border: none; border-top: 1px solid var(--border-color, #ccc);">

    <h3>Diferenças entre revisões</h3>
    <div id="diff-output">
        {$preDiffHtml}
    </div>
</div>

{$clientJs}
HTML;

        $historyPage->content = new \Indieinabox\Page\Content($bodyContent);

        // Render via theme
        $themeDir = $site->paths->themeDir ?? 'theme';
        $fullThemeDir = str_starts_with($themeDir, DIRECTORY_SEPARATOR)
            ? $themeDir
            : ($base . DIRECTORY_SEPARATOR . $themeDir);

        ob_start();
        $p = $historyPage;
        $siteObj = $site;
        $pagesObj = $pages;

        $viewVars = [
            'page' => $historyPage,
            'p' => $historyPage,
            'site' => $site,
            'pages' => $pages,
            'headerLinks' => [],
            'footerLinks' => [],
        ];

        ThemeManager::loadView(
            $fullThemeDir . '/views/' . ($historyPage->metadata->layout ?? 'page') . '.php',
            $viewVars
        );
        $fullHtml = ob_get_clean();

        file_put_contents($destFile, $fullHtml);
        SiteBuilder::addManifest($destFile);
        echo 'Built ' . str_replace($outDir . DIRECTORY_SEPARATOR, '', $destFile) . "\n";
    }

    /**
     * Publishes historical snapshots in HTML: /{slug}/v/{version}/index.html.
     *
     * @param Page $page
     * @param Site $site
     * @param Pages $pages
     * @param array<string, mixed> $history
     * @return void
     */
    public static function publishHtmlSnapshots(Page $page, Site $site, Pages $pages, array $history): void
    {
        $base = $site->paths->baseDir;
        $slugPath = trim(str_replace('/', DIRECTORY_SEPARATOR, $page->slug), DIRECTORY_SEPARATOR);
        $outDir = $base . DIRECTORY_SEPARATOR . $site->paths->outputDirHtml;
        $themeDir = $site->paths->themeDir ?? 'theme';

        $versions = $history['versions'] ?? [];
        $astParser = new ASTParser();
        $htmlRenderer = new HtmlRenderer($page);

        foreach ($versions as $v) {
            $versionTag = (string) $v['version'];
            $snapshotDir = $outDir . DIRECTORY_SEPARATOR . $slugPath . DIRECTORY_SEPARATOR . 'v' . DIRECTORY_SEPARATOR . $versionTag;
            if (!is_dir($snapshotDir)) {
                @mkdir($snapshotDir, 0777, true);
            }
            $destFile = $snapshotDir . DIRECTORY_SEPARATOR . 'index.html';

            $snapshotPage = clone $page;
            $snapshotPage->relpath .= '../../';
            $snapshotPage->slug .= '/v/' . $versionTag;
            $snapshotPage->metadata->is_old_version = true;
            $snapshotPage->metadata->old_version_date = (string) ($v['date'] ?? '');
            $snapshotPage->metadata->version = $versionTag;

            // Render Markdown body of that specific historical version
            $rawBody = (string) ($v['content'] ?? '');
            $ast = $astParser->parse($rawBody);
            $renderedContent = $htmlRenderer->render($ast);

            // Add top MediaWiki warning banner
            $banner = <<<HTML
<div class="wiki-revision-notice" role="alert" style="margin-bottom: 1.5em; padding: 1em; border-left: 4px solid var(--accent, #e36209); background: var(--notice-bg, rgba(227, 98, 9, 0.08)); border-radius: 4px;">
    <strong>⚠️ Esta é uma versão antiga desta página</strong> (editada em <code>{$v['date']}</code> — <code>{$versionTag}</code>).
    <br>
    <a href="../../">← Ir para a versão mais recente atual</a> · <a href="../../history/">Ver histórico de alterações</a>
</div>
HTML;

            $snapshotPage->content = new \Indieinabox\Page\Content($banner . $renderedContent);

            ob_start();
            $viewVars = [
                'page' => $snapshotPage,
                'p' => $snapshotPage,
                'site' => $site,
                'pages' => $pages,
                'headerLinks' => [],
                'footerLinks' => [],
            ];
            $fullThemeDir = str_starts_with($themeDir, DIRECTORY_SEPARATOR)
                ? $themeDir
                : ($base . DIRECTORY_SEPARATOR . $themeDir);

            ThemeManager::loadView(
                $fullThemeDir . '/views/' . ($snapshotPage->metadata->layout ?? 'page') . '.php',
                $viewVars
            );
            $fullHtml = ob_get_clean();

            file_put_contents($destFile, $fullHtml);
            SiteBuilder::addManifest($destFile);
            echo 'Built ' . str_replace($outDir . DIRECTORY_SEPARATOR, '', $destFile) . "\n";
        }
    }

    /**
     * Publishes Gemini history: public_gemini/{slug}/history/index.gmi.
     *
     * @param Page $page
     * @param Site $site
     * @param array<string, mixed> $history
     * @return void
     */
    public static function publishGeminiHistory(Page $page, Site $site, array $history): void
    {
        $base = $site->paths->baseDir;
        $slugPath = trim(str_replace('/', DIRECTORY_SEPARATOR, $page->slug), DIRECTORY_SEPARATOR);
        $outDirGemini = $base . DIRECTORY_SEPARATOR . $site->paths->outputDirGemini;

        $historyDir = $outDirGemini . DIRECTORY_SEPARATOR . $slugPath . DIRECTORY_SEPARATOR . 'history';
        if (!is_dir($historyDir)) {
            @mkdir($historyDir, 0777, true);
        }
        $destFile = $historyDir . DIRECTORY_SEPARATOR . 'index.gmi';

        $title = $page->title;
        $versions = array_reverse($history['versions'] ?? []);

        $gmi = "# Histórico de Revisões: {$title}\n\n";
        $gmi .= "=> ../ Voltar para a versão atual\n\n";
        $gmi .= "### Lista de Revisões\n\n";

        foreach ($versions as $idx => $v) {
            $isLatest = ($idx === 0) ? ' [Atual]' : '';
            $versionTag = (string) $v['version'];
            $date = (string) ($v['date'] ?? '');
            $note = !empty($v['note']) ? ' - ' . $v['note'] : '';
            $bytes = (int) ($v['bytes'] ?? 0);

            $gmi .= "=> ../v/{$versionTag}/ {$versionTag} ({$date}){$isLatest} ({$bytes} bytes){$note}\n";
        }

        $gmi .= "\n=> / Início\n";

        file_put_contents($destFile, $gmi);
        SiteBuilder::addManifest($destFile);
        echo 'Built ' . str_replace($outDirGemini . DIRECTORY_SEPARATOR, '', $destFile) . "\n";
    }

    /**
     * Publishes Gemini snapshots: public_gemini/{slug}/v/{version}/index.gmi.
     *
     * @param Page $page
     * @param Site $site
     * @param array<string, mixed> $history
     * @return void
     */
    public static function publishGeminiSnapshots(Page $page, Site $site, array $history): void
    {
        $base = $site->paths->baseDir;
        $slugPath = trim(str_replace('/', DIRECTORY_SEPARATOR, $page->slug), DIRECTORY_SEPARATOR);
        $outDirGemini = $base . DIRECTORY_SEPARATOR . $site->paths->outputDirGemini;

        $versions = $history['versions'] ?? [];
        $astParser = new ASTParser();
        $gemtextRenderer = new GemtextRenderer($page);

        foreach ($versions as $v) {
            $versionTag = (string) $v['version'];
            $snapshotDir = $outDirGemini . DIRECTORY_SEPARATOR . $slugPath . DIRECTORY_SEPARATOR . 'v' . DIRECTORY_SEPARATOR . $versionTag;
            if (!is_dir($snapshotDir)) {
                @mkdir($snapshotDir, 0777, true);
            }
            $destFile = $snapshotDir . DIRECTORY_SEPARATOR . 'index.gmi';

            $rawBody = (string) ($v['content'] ?? '');
            $ast = $astParser->parse($rawBody);
            $renderedBody = $gemtextRenderer->render($ast);

            $gmi = "# [Versão Antiga] {$page->title}\n";
            $gmi .= "> ⚠️ Esta é uma versão antiga desta página ({$versionTag} de {$v['date']}).\n\n";
            $gmi .= "=> ../../ ← Ir para a versão atual\n";
            $gmi .= "=> ../../history/ Ver histórico de alterações\n\n";
            $gmi .= $renderedBody;
            $gmi .= "\n=> / Início\n";

            file_put_contents($destFile, $gmi);
            SiteBuilder::addManifest($destFile);
            echo 'Built ' . str_replace($outDirGemini . DIRECTORY_SEPARATOR, '', $destFile) . "\n";
        }
    }

    /**
     * Publishes Gopher history: public_gopher/{slug}/history/gophermap.
     *
     * @param Page $page
     * @param Site $site
     * @param array<string, mixed> $history
     * @return void
     */
    public static function publishGopherHistory(Page $page, Site $site, array $history): void
    {
        $base = $site->paths->baseDir;
        $slugPath = trim(str_replace('/', DIRECTORY_SEPARATOR, $page->slug), DIRECTORY_SEPARATOR);
        $outDirGopher = $base . DIRECTORY_SEPARATOR . $site->paths->outputDirGopher;

        $historyDir = $outDirGopher . DIRECTORY_SEPARATOR . $slugPath . DIRECTORY_SEPARATOR . 'history';
        if (!is_dir($historyDir)) {
            @mkdir($historyDir, 0777, true);
        }
        $destFile = $historyDir . DIRECTORY_SEPARATOR . 'gophermap';

        $host = 'gopher.example.com';
        if ($site->metadata->fqdn) {
            $parsed = parse_url($site->metadata->fqdn);
            $host = $parsed['host'] ?? $host;
        }

        $formatInfo = function (string $text): string {
            return "i{$text}\t\t(null)\t0\r\n";
        };

        $content = $formatInfo("=== Histórico de Revisões: {$page->title} ===");
        $content .= "1[Voltar para a versão atual]\t/{$slugPath}/\t{$host}\t70\r\n";
        $content .= $formatInfo('');
        $content .= $formatInfo('Revisões registradas:');

        $versions = array_reverse($history['versions'] ?? []);
        foreach ($versions as $idx => $v) {
            $isLatest = ($idx === 0) ? ' [Atual]' : '';
            $versionTag = (string) $v['version'];
            $date = (string) ($v['date'] ?? '');
            $note = !empty($v['note']) ? ' - ' . $v['note'] : '';
            $bytes = (int) ($v['bytes'] ?? 0);

            $label = "{$versionTag} ({$date}){$isLatest} ({$bytes} bytes){$note}";
            $content .= "1{$label}\t/{$slugPath}/v/{$versionTag}/\t{$host}\t70\r\n";
        }

        $content .= $formatInfo('');
        $content .= "1Início\t/\t{$host}\t70\r\n";

        file_put_contents($destFile, $content);
        SiteBuilder::addManifest($destFile);
        echo 'Built ' . str_replace($outDirGopher . DIRECTORY_SEPARATOR, '', $destFile) . "\n";
    }

    /**
     * Publishes Gopher snapshots: public_gopher/{slug}/v/{version}/gophermap.
     *
     * @param Page $page
     * @param Site $site
     * @param array<string, mixed> $history
     * @return void
     */
    public static function publishGopherSnapshots(Page $page, Site $site, array $history): void
    {
        $base = $site->paths->baseDir;
        $slugPath = trim(str_replace('/', DIRECTORY_SEPARATOR, $page->slug), DIRECTORY_SEPARATOR);
        $outDirGopher = $base . DIRECTORY_SEPARATOR . $site->paths->outputDirGopher;

        $host = 'gopher.example.com';
        if ($site->metadata->fqdn) {
            $parsed = parse_url($site->metadata->fqdn);
            $host = $parsed['host'] ?? $host;
        }

        $versions = $history['versions'] ?? [];
        $astParser = new ASTParser();
        $gophermapRenderer = new GophermapRenderer($host, 70, $page);

        $formatInfo = function (string $text): string {
            return "i{$text}\t\t(null)\t0\r\n";
        };

        foreach ($versions as $v) {
            $versionTag = (string) $v['version'];
            $snapshotDir = $outDirGopher . DIRECTORY_SEPARATOR . $slugPath . DIRECTORY_SEPARATOR . 'v' . DIRECTORY_SEPARATOR . $versionTag;
            if (!is_dir($snapshotDir)) {
                @mkdir($snapshotDir, 0777, true);
            }
            $destFile = $snapshotDir . DIRECTORY_SEPARATOR . 'gophermap';

            $rawBody = (string) ($v['content'] ?? '');
            $ast = $astParser->parse($rawBody);
            $renderedGopher = $gophermapRenderer->render($ast);

            $content = $formatInfo("=== [Versão Antiga] {$page->title} ===");
            $content .= $formatInfo("AVISO: Esta é uma versão antiga ({$versionTag} de {$v['date']}).");
            $content .= "1[Ir para a versão mais recente]\t/{$slugPath}/\t{$host}\t70\r\n";
            $content .= "1[Ver histórico de revisões]\t/{$slugPath}/history/\t{$host}\t70\r\n";
            $content .= $formatInfo('');
            $content .= $renderedGopher;
            $content .= $formatInfo('');
            $content .= "1Início\t/\t{$host}\t70\r\n";

            file_put_contents($destFile, $content);
            SiteBuilder::addManifest($destFile);
            echo 'Built ' . str_replace($outDirGopher . DIRECTORY_SEPARATOR, '', $destFile) . "\n";
        }
    }
}
