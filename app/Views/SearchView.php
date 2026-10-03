<?php

declare(strict_types=1);

namespace Indieinabox\Views;

use Indieinabox\Site\Site;

/**
 * Class SearchView
 *
 * Renders the responsive HTML user interface for full-text search across posts,
 * garden taxonomy, and ingested feeds using SQLite FTS5.
 */
class SearchView
{
    /**
     * Renders the search page HTML.
     *
     * @param array<string, mixed> $data Search results payload from SearchService.
     * @param Site|null $site
     * @return string
     */
    public static function render(array $data, ?Site $site = null): string
    {
        $query = htmlspecialchars((string) ($data['query'] ?? ''));
        $total = (int) ($data['total'] ?? 0);
        $limit = (int) ($data['limit'] ?? 20);
        $offset = (int) ($data['offset'] ?? 0);
        $results = $data['results'] ?? [];
        $currentType = htmlspecialchars((string) ($data['type'] ?? ''));

        $siteName = htmlspecialchars($site?->metadata->sitename ?? 'IndieInABox');
        $siteFqdn = htmlspecialchars(rtrim($site?->metadata->fqdn ?? '/', '/'));

        // Type filter pills helper
        $types = [
            '' => '🔍 Tudo',
            'post' => '📝 Posts',
            'garden' => '🌱 Digital Garden',
            'feed' => '📡 Feeds',
        ];

        $filterPills = '';
        foreach ($types as $key => $label) {
            $activeClass = ($currentType === $key) ? ' active' : '';
            $url = '/search?q=' . urlencode((string) ($data['query'] ?? ''));
            if ($key !== '') {
                $url .= '&type=' . urlencode($key);
            }
            $filterPills .= "<a href=\"{$url}\" class=\"pill{$activeClass}\">{$label}</a>";
        }

        // Results listing
        $resultsHtml = '';
        if (empty($results)) {
            if ($query !== '') {
                $resultsHtml = '<div class="empty-state">
                    <p>Nenhum resultado encontrado para <strong>"' . $query . '"</strong>.</p>
                    <p class="hint">Tente buscar por outras palavras, raízes de palavras ou use aspas para buscar frases exatas.</p>
                </div>';
            } else {
                $resultsHtml = '<div class="empty-state">
                    <p>Digite um termo no campo acima para pesquisar em artigos, anotações do jardim e feeds.</p>
                </div>';
            }
        } else {
            foreach ($results as $item) {
                $itemTitle = htmlspecialchars((string) ($item['title'] ?? 'Sem título'));
                $itemUrl = htmlspecialchars((string) ($item['url'] ?? '#'));
                $itemType = (string) ($item['type'] ?? 'post');
                $itemAuthor = htmlspecialchars((string) ($item['author'] ?? ''));
                $itemDate = substr((string) ($item['published_at'] ?? ''), 0, 10);
                $snippet = (string) ($item['snippet'] ?? '');

                $badgeClass = match ($itemType) {
                    'garden' => 'badge-garden',
                    'feed' => 'badge-feed',
                    default => 'badge-post',
                };
                $badgeLabel = match ($itemType) {
                    'garden' => '🌱 Garden',
                    'feed' => '📡 Feed',
                    default => '📝 Post',
                };

                $tagsHtml = '';
                if (!empty($item['tags']) && is_array($item['tags'])) {
                    $tagsHtml = '<div class="item-tags">';
                    foreach ($item['tags'] as $tag) {
                        $tagsHtml .= '<span class="tag">#' . htmlspecialchars((string) $tag) . '</span> ';
                    }
                    $tagsHtml .= '</div>';
                }

                $metaInfo = [];
                if ($itemDate !== '') {
                    $metaInfo[] = $itemDate;
                }
                if ($itemAuthor !== '' && $itemAuthor !== 'Unknown') {
                    $metaInfo[] = 'por ' . $itemAuthor;
                }
                $metaStr = implode(' • ', $metaInfo);

                $resultsHtml .= "
                <article class=\"search-item\">
                    <div class=\"item-header\">
                        <span class=\"badge {$badgeClass}\">{$badgeLabel}</span>
                        <h3 class=\"item-title\"><a href=\"{$itemUrl}\">{$itemTitle}</a></h3>
                    </div>
                    <div class=\"item-meta\">{$metaStr}</div>
                    <p class=\"item-snippet\">\"{$snippet}\"</p>
                    {$tagsHtml}
                </article>";
            }
        }

        // Pagination
        $paginationHtml = '';
        if ($total > $limit) {
            $prevOffset = max(0, $offset - $limit);
            $nextOffset = $offset + $limit;

            $prevUrl = '/search?q=' . urlencode((string) ($data['query'] ?? '')) . '&offset=' . $prevOffset;
            $nextUrl = '/search?q=' . urlencode((string) ($data['query'] ?? '')) . '&offset=' . $nextOffset;
            if ($currentType !== '') {
                $prevUrl .= '&type=' . urlencode($currentType);
                $nextUrl .= '&type=' . urlencode($currentType);
            }

            $paginationHtml = '<div class="pagination">';
            if ($offset > 0) {
                $paginationHtml .= "<a href=\"{$prevUrl}\" class=\"btn\">&larr; Anterior</a>";
            }
            $currentCount = count($results);
            $startNum = $offset + 1;
            $endNum = $offset + $currentCount;
            $paginationHtml .= "<span class=\"page-info\">Exibindo {$startNum}-{$endNum} de {$total}</span>";
            if ($nextOffset < $total) {
                $paginationHtml .= "<a href=\"{$nextUrl}\" class=\"btn\">Próxima &rarr;</a>";
            }
            $paginationHtml .= '</div>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Busca — {$siteName}</title>
    <style>
        :root {
            --bg-color: #0d1117;
            --surface-color: #161b22;
            --border-color: #30363d;
            --text-color: #c9d1d9;
            --text-heading: #f0f6fc;
            --accent-color: #58a6ff;
            --accent-hover: #79c0ff;
            --mark-bg: #d29922;
            --mark-color: #0d1117;
            --font-stack: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --font-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
        }
        @media (prefers-color-scheme: light) {
            :root {
                --bg-color: #f6f8fa;
                --surface-color: #ffffff;
                --border-color: #d0d7de;
                --text-color: #24292f;
                --text-heading: #1f2328;
                --accent-color: #0969da;
                --accent-hover: #1b7cfc;
                --mark-bg: #fff8c5;
                --mark-color: #24292f;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font-stack);
            background: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 840px;
            margin: 0 auto;
        }
        header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
        }
        header a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 600;
        }
        h1 {
            color: var(--text-heading);
            font-size: 1.75rem;
            letter-spacing: -0.02em;
        }
        .search-box-form {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }
        .search-input {
            flex: 1;
            padding: 0.75rem 1rem;
            font-size: 1.1rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--surface-color);
            color: var(--text-heading);
            outline: none;
        }
        .search-input:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.2);
        }
        .btn-submit {
            padding: 0.75rem 1.5rem;
            background: var(--accent-color);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
        }
        .btn-submit:hover {
            background: var(--accent-hover);
        }
        .filter-pills {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .pill {
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            background: var(--surface-color);
            color: var(--text-color);
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.15s;
        }
        .pill:hover, .pill.active {
            background: var(--accent-color);
            color: #fff;
            border-color: var(--accent-color);
        }
        .stats-bar {
            font-size: 0.9rem;
            color: var(--text-color);
            margin-bottom: 1.5rem;
        }
        .search-item {
            background: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: border-color 0.15s;
        }
        .search-item:hover {
            border-color: var(--accent-color);
        }
        .item-header {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.35rem;
        }
        .item-title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        .item-title a {
            color: var(--text-heading);
            text-decoration: none;
        }
        .item-title a:hover {
            color: var(--accent-color);
            text-decoration: underline;
        }
        .item-meta {
            font-size: 0.8rem;
            color: var(--text-color);
            opacity: 0.8;
            margin-bottom: 0.6rem;
        }
        .item-snippet {
            font-size: 0.95rem;
            line-height: 1.5;
            color: var(--text-color);
        }
        mark {
            background: var(--mark-bg);
            color: var(--mark-color);
            padding: 0.1em 0.25em;
            border-radius: 3px;
            font-weight: 600;
        }
        .badge {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .badge-garden { background: #238636; color: #fff; }
        .badge-post { background: #1f6feb; color: #fff; }
        .badge-feed { background: #9e6a03; color: #fff; }
        .item-tags {
            margin-top: 0.6rem;
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .tag {
            font-size: 0.75rem;
            color: var(--accent-color);
            background: rgba(88, 166, 255, 0.1);
            padding: 0.15rem 0.4rem;
            border-radius: 3px;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background: var(--surface-color);
            border: 1px dashed var(--border-color);
            border-radius: 8px;
        }
        .hint {
            font-size: 0.85rem;
            opacity: 0.7;
            margin-top: 0.5rem;
        }
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        .btn {
            padding: 0.5rem 1rem;
            background: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-heading);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .btn:hover {
            border-color: var(--accent-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>⚡ Busca em Tempo Real</h1>
            <a href="/">← Início</a>
        </header>

        <form action="/search" method="GET" class="search-box-form">
            <input type="search" name="q" value="{$query}" placeholder="Pesquisar artigos, notas do jardim e feeds..." autofocus class="search-input" />
            <input type="hidden" name="type" value="{$currentType}" />
            <button type="submit" class="btn-submit">Buscar</button>
        </form>

        <div class="filter-pills">
            {$filterPills}
        </div>

        <div class="stats-bar">
            Encontrados: <strong>{$total}</strong> resultado(s)
        </div>

        <div class="results-list">
            {$resultsHtml}
        </div>

        {$paginationHtml}
    </div>
</body>
</html>
HTML;
    }
}
