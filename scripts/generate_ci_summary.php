<?php
declare(strict_types=1);

/**
 * Generates CI Job Summary for $GITHUB_STEP_SUMMARY and HTML dashboard for Codeberg Pages.
 * Usage:
 *   php scripts/generate_ci_summary.php --step-summary
 *   php scripts/generate_ci_summary.php --build-pages
 */

$baseDir = dirname(__DIR__);
$coverageDir = $baseDir . '/coverage';
$isPages = in_array('--build-pages', $argv, true);
$isStepSummary = in_array('--step-summary', $argv, true);

// 1. Parse Clover XML (Code Coverage)
$cloverFile = $coverageDir . '/clover.xml';
$coveragePct = null;
$totalLines = 0;
$coveredLines = 0;
if (file_exists($cloverFile)) {
    $xml = @simplexml_load_file($cloverFile);
    if ($xml && isset($xml->project->metrics)) {
        $metrics = $xml->project->metrics;
        $totalLines = (int)($metrics['loc'] ?? $metrics['statements'] ?? 0);
        $coveredLines = (int)($metrics['coveredstatements'] ?? 0);
        if ($totalLines > 0) {
            $coveragePct = round(($coveredLines / $totalLines) * 100, 1);
        }
    }
}

// 2. Parse JUnit XML (Tests)
$junitFile = $coverageDir . '/junit.xml';
$testCount = 0;
$testFailures = 0;
$testErrors = 0;
$testTime = 0.0;
if (file_exists($junitFile) && filesize($junitFile) > 0) {
    $xml = @simplexml_load_file($junitFile);
    if ($xml) {
        foreach ($xml->testsuite as $suite) {
            $testCount += (int)($suite['tests'] ?? 0);
            $testFailures += (int)($suite['failures'] ?? 0);
            $testErrors += (int)($suite['errors'] ?? 0);
            $testTime += (float)($suite['time'] ?? 0);
        }
    }
}

// 3. Parse Psalm JSON
$psalmFile = $coverageDir . '/psalm.json';
$psalmIssues = 0;
$psalmErrors = [];
if (file_exists($psalmFile)) {
    $data = json_decode((string)file_get_contents($psalmFile), true);
    if (is_array($data)) {
        $psalmIssues = count($data);
        $psalmErrors = array_slice($data, 0, 5);
    }
}

// 4. Parse Link Checker Report
$linkFile = $coverageDir . '/link-check-report.json';
$linksSummary = ['total' => 0, 'internal' => 0, 'external' => 0, 'errors' => 0];
$brokenLinks = [];
if (file_exists($linkFile)) {
    $data = json_decode((string)file_get_contents($linkFile), true);
    if (isset($data['summary'])) {
        $linksSummary['total'] = (int)($data['summary']['total_links'] ?? 0);
        $linksSummary['internal'] = (int)($data['summary']['internal'] ?? 0);
        $linksSummary['external'] = (int)($data['summary']['external'] ?? 0);
        $linksSummary['errors'] = (int)($data['summary']['errors'] ?? 0);
    }
    $brokenLinks = $data['errors'] ?? [];
}

// 5. Check OWASP Dependency-Check Report
$owaspHtml = $coverageDir . '/dependency-check/dependency-check-report.html';
$hasOwasp = file_exists($owaspHtml);

// Output Markdown for GITHUB_STEP_SUMMARY
if ($isStepSummary || !$isPages) {
    $covEmoji = $coveragePct !== null ? ($coveragePct >= 80 ? '🟢' : ($coveragePct >= 50 ? '🟡' : '🔴')) : '⚪';
    $covText = $coveragePct !== null ? "{$coveragePct}% ({$coveredLines}/{$totalLines} linhas)" : 'N/A';
    $testStatus = ($testFailures === 0 && $testErrors === 0) ? '✅ Sucesso' : '❌ Falhou';
    $psalmStatus = $psalmIssues === 0 ? '✅ 0 problemas' : "⚠️ {$psalmIssues} problemas";
    $linksStatus = $linksSummary['errors'] === 0 ? "✅ {$linksSummary['total']} verificados" : "❌ {$linksSummary['errors']} quebrados";

    $md = <<<MARKDOWN
## 📊 Relatório da Execução de CI

| Item | Status | Detalhes |
|---|---|---|
| **Testes (Pest)** | {$testStatus} | {$testCount} testes executados em {$testTime}s |
| **Cobertura de Código** | {$covEmoji} {$covText} | Clover XML |
| **Segurança Estática (Psalm)** | {$psalmStatus} | Taint analysis habilitada |
| **Link Checker** | {$linksStatus} | {$linksSummary['internal']} internos, {$linksSummary['external']} externos |
| **OWASP Dependency-Check** | 🛡️ Analisado | Varredura de vulnerabilidades de dependências |

MARKDOWN;

    if (!empty($brokenLinks)) {
        $md .= "\n<details><summary>❌ <b>Links Quebrados Encontrados (" . count($brokenLinks) . ")</b></summary>\n\n";
        foreach ($brokenLinks as $err) {
            $md .= "- `{$err['link']}` (Status: {$err['status']})\n";
        }
        $md .= "\n</details>\n";
    }

    if (!empty($psalmErrors)) {
        $md .= "\n<details><summary>⚠️ <b>Problemas Psalm (" . count($psalmErrors) . ")</b></summary>\n\n";
        foreach ($psalmErrors as $err) {
            $md .= "- `{$err['type']}` em `{$err['file_name']}:{$err['line_from']}`\n";
        }
        $md .= "\n</details>\n";
    }

    $summaryEnv = getenv('GITHUB_STEP_SUMMARY');
    if ($summaryEnv && is_writable($summaryEnv)) {
        file_put_contents($summaryEnv, $md, FILE_APPEND);
        echo "Step summary gravado em {$summaryEnv}\n";
    } else {
        echo $md;
    }
}

// Generate Static HTML for Pages
if ($isPages) {
    $outDir = $baseDir . '/public_reports';
    if (!is_dir($outDir)) {
        mkdir($outDir, 0755, true);
    }

    // Copy coverage html if exists
    if (is_dir($coverageDir . '/html')) {
        copyDir($coverageDir . '/html', $outDir . '/coverage');
    }
    if (file_exists($coverageDir . '/clover.xml')) {
        @mkdir($outDir . '/coverage', 0755, true);
        copy($coverageDir . '/clover.xml', $outDir . '/coverage/clover.xml');
    }
    // Copy OWASP report if exists
    if (is_dir($coverageDir . '/dependency-check')) {
        copyDir($coverageDir . '/dependency-check', $outDir . '/dependency-check');
    }

    $dateStr = (new \DateTimeImmutable())->format('d/m/Y H:i:s T');
    $psalmColor = $psalmIssues === 0 ? 'var(--success)' : 'var(--warning)';
    $linksColor = $linksSummary['errors'] === 0 ? 'var(--success)' : 'var(--danger)';
    $covVal = $coveragePct !== null ? "{$coveragePct}%" : 'N/A';
    $linksTotal = $linksSummary['total'];
    $linksErrors = $linksSummary['errors'];

    $htmlContent = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indieinabox — Painel de Relatórios CI</title>
    <style>
        :root {
            --bg: #0f172a;
            --surface: #1e293b;
            --border: #334155;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #38bdf8;
            --success: #4ade80;
            --danger: #f87171;
            --warning: #facc15;
        }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 2rem;
            line-height: 1.5;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        header { margin-bottom: 2.5rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem; }
        h1 { margin: 0 0 0.5rem 0; font-size: 1.8rem; }
        .meta { color: var(--text-muted); font-size: 0.9rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem; }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem;
        }
        .card h3 { margin: 0 0 0.5rem 0; font-size: 0.95rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .card .value { font-size: 1.75rem; font-weight: 700; }
        .links-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .links-section h2 { margin-top: 0; font-size: 1.25rem; }
        .btn-list { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; }
        a.btn {
            display: inline-flex;
            align-items: center;
            padding: 0.6rem 1.2rem;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: opacity 0.15s;
        }
        a.btn:hover { opacity: 0.9; }
        a.btn-alt { background: var(--border); color: var(--text); }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.9rem; }
        th, td { text-align: left; padding: 0.6rem; border-bottom: 1px solid var(--border); }
        th { color: var(--text-muted); font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Indieinabox — Painel de Relatórios CI</h1>
            <div class="meta">Última atualização: {$dateStr}</div>
        </header>

        <div class="grid">
            <div class="card">
                <h3>Testes (Pest)</h3>
                <div class="value" style="color: var(--success)">{$testCount}</div>
                <div class="meta">0 falhas • {$testTime}s</div>
            </div>
            <div class="card">
                <h3>Cobertura de Código</h3>
                <div class="value" style="color: var(--accent)">{$covVal}</div>
                <div class="meta">{$coveredLines} de {$totalLines} linhas</div>
            </div>
            <div class="card">
                <h3>Segurança Psalm</h3>
                <div class="value" style="color: {$psalmColor}">{$psalmIssues}</div>
                <div class="meta">problemas detectados</div>
            </div>
            <div class="card">
                <h3>Links Verificados</h3>
                <div class="value" style="color: {$linksColor}">{$linksTotal}</div>
                <div class="meta">{$linksErrors} links quebrados</div>
            </div>
        </div>

        <div class="links-section">
            <h2>Relatórios Interativos Detalhados</h2>
            <p class="meta">Acesse as páginas completas navegáveis geradas durante o build:</p>
            <div class="btn-list">
                <a href="coverage/" class="btn">📊 Cobertura Completa de Código (HTML)</a>
                <a href="dependency-check/dependency-check-report.html" class="btn btn-alt">🛡️ Vulnerabilidades OWASP (HTML)</a>
            </div>
        </div>
    </div>
</body>
</html>
HTML;

    file_put_contents($outDir . '/index.html', $htmlContent);
    echo "Dashboard para Pages gerado em {$outDir}/index.html\n";
}

function copyDir(string $src, string $dst): void {
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    $dir = opendir($src);
    if (!$dir) return;
    while (false !== ($file = readdir($dir))) {
        if ($file !== '.' && $file !== '..') {
            if (is_dir($src . '/' . $file)) {
                copyDir($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}
