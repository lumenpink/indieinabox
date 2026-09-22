<?php

declare(strict_types=1);

namespace Indieinabox\Views;

use Indieinabox\Site\Site;

/**
 * Class DiscoveryView
 *
 * Renders the responsive HTML UI for exploring the federated Global Timeline.
 */
class DiscoveryView
{
    /**
     * Renders the discovery explorer UI.
     *
     * @param array<string, mixed> $data
     * @param Site|null $site
     * @return string
     */
    public static function render(array $data, ?Site $site = null): string
    {
        $items = $data['items'] ?? [];
        $total = (int) ($data['total'] ?? 0);
        $networks = $data['networks'] ?? [];
        $tags = $data['tags'] ?? [];
        $currentNetwork = (string) ($data['filters']['network'] ?? '');
        $currentTag = (string) ($data['filters']['tag'] ?? '');
        $currentSearch = (string) ($data['filters']['search'] ?? '');
        $limit = (int) ($data['limit'] ?? 20);
        $offset = (int) ($data['offset'] ?? 0);

        $siteName = htmlspecialchars($site?->metadata->sitename ?? 'indieinabox');
        $siteFqdn = htmlspecialchars(rtrim($site?->metadata->fqdn ?? '/', '/'));

        // Network badge styling & labels
        $networkLabels = [
            'activitypub' => ['name' => 'ActivityPub', 'class' => 'badge-ap'],
            'ap' => ['name' => 'ActivityPub', 'class' => 'badge-ap'],
            'twtxt' => ['name' => 'Twtxt', 'class' => 'badge-twtxt'],
            'rss' => ['name' => 'RSS/Feed', 'class' => 'badge-rss'],
            'local' => ['name' => 'Local Post', 'class' => 'badge-local'],
        ];

        // Build base URL without offset for filter links
        $buildUrl = function (array $newParams) use ($currentNetwork, $currentTag, $currentSearch): string {
            $params = [];
            if ($currentNetwork !== '') {
                $params['network'] = $currentNetwork;
            }
            if ($currentTag !== '') {
                $params['tag'] = $currentTag;
            }
            if ($currentSearch !== '') {
                $params['q'] = $currentSearch;
            }
            foreach ($newParams as $k => $v) {
                if ($v === null || $v === '') {
                    unset($params[$k]);
                } else {
                    $params[$k] = $v;
                }
            }
            $query = http_build_query($params);
            return '/discover' . ($query !== '' ? '?' . $query : '');
        };

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Timeline & Discovery - <?= $siteName ?></title>
    <link rel="alternate" type="application/jf2+json" href="/discover?format=jf2" title="Global Timeline (JF2)">
    <link rel="alternate" type="application/rss+xml" href="/discover?format=rss" title="Global Timeline (RSS 2.0)">
    <style>
        :root {
            --bg: #0f1117;
            --surface: #181b26;
            --surface-hover: #222634;
            --border: #2c3245;
            --text: #e2e8f0;
            --text-muted: #8e9bb0;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --badge-ap: #a855f7;
            --badge-twtxt: #0ea5e9;
            --badge-rss: #f97316;
            --badge-local: #10b981;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
            padding: 20px 16px;
        }
        .container {
            max-width: 780px;
            margin: 0 auto;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
        }
        .logo a {
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .header-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            margin-left: 16px;
            transition: color 0.2s;
        }
        .header-links a:hover { color: #fff; }
        .hero {
            margin-bottom: 24px;
        }
        .hero h1 { font-size: 1.8rem; margin-bottom: 6px; font-weight: 800; }
        .hero p { color: var(--text-muted); font-size: 0.95rem; }

        .search-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 8px;
        }
        .search-bar input {
            flex: 1;
            background: var(--surface);
            border: 1px solid var(--border);
            color: #fff;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.95rem;
            outline: none;
        }
        .search-bar input:focus { border-color: var(--primary); }
        .search-bar button {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .search-bar button:hover { background: var(--primary-hover); }

        .filters-nav {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .filter-btn {
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 6px 14px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.85rem;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .filter-btn:hover { background: var(--surface-hover); color: #fff; }
        .filter-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            font-weight: 600;
        }
        .filter-count {
            opacity: 0.75;
            font-size: 0.75rem;
        }

        .active-tag-banner {
            background: rgba(99, 102, 241, 0.15);
            border: 1px solid var(--primary);
            padding: 8px 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
        }
        .active-tag-banner a { color: #fff; text-decoration: none; font-weight: bold; }

        .timeline { display: flex; flex-direction: column; gap: 16px; }
        .entry-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px;
            transition: border-color 0.2s;
        }
        .entry-card:hover { border-color: #3f4761; }
        .entry-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .author-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #2a2f42;
            object-fit: cover;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--text-muted);
            overflow: hidden;
        }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .author-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: #fff;
            text-decoration: none;
        }
        .author-name:hover { text-decoration: underline; }
        .entry-date {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .badges { display: flex; align-items: center; gap: 8px; }
        .badge {
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .badge-ap { background: rgba(168, 85, 247, 0.2); color: var(--badge-ap); border: 1px solid rgba(168, 85, 247, 0.4); }
        .badge-twtxt { background: rgba(14, 165, 233, 0.2); color: var(--badge-twtxt); border: 1px solid rgba(14, 165, 233, 0.4); }
        .badge-rss { background: rgba(249, 115, 22, 0.2); color: var(--badge-rss); border: 1px solid rgba(249, 115, 22, 0.4); }
        .badge-local { background: rgba(16, 185, 129, 0.2); color: var(--badge-local); border: 1px solid rgba(16, 185, 129, 0.4); }

        .entry-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #fff;
        }
        .entry-content {
            font-size: 0.95rem;
            color: #cbd5e1;
            word-break: break-word;
            margin-bottom: 12px;
        }
        .entry-content a { color: #818cf8; text-decoration: none; }
        .entry-content a:hover { text-decoration: underline; }

        .cw-container {
            background: rgba(0,0,0,0.3);
            border: 1px dashed var(--border);
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 12px;
        }
        .cw-summary { cursor: pointer; font-weight: 600; color: #f59e0b; outline: none; }

        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 12px;
            margin-bottom: 12px;
        }
        .gallery img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
        }
        .tag-pill {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-muted);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .tag-pill:hover { background: var(--surface-hover); color: #fff; }

        .entry-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.82rem;
            color: var(--text-muted);
        }
        .entry-footer a { color: var(--text-muted); text-decoration: none; }
        .entry-footer a:hover { color: #fff; }

        .pagination {
            display: flex;
            justify-content: space-between;
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        .pagination a {
            background: var(--surface);
            border: 1px solid var(--border);
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .pagination a:hover { background: var(--surface-hover); }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--surface);
            border-radius: 12px;
            border: 1px dashed var(--border);
            color: var(--text-muted);
        }
    </style>
</head>
<body>
<div class="container">
    <header>
        <div class="logo">
            <a href="/">🌐 <?= $siteName ?></a>
        </div>
        <div class="header-links">
            <a href="/discover">Global</a>
            <a href="/discover?format=jf2" target="_blank">JF2 JSON</a>
            <a href="/discover?format=rss" target="_blank">RSS 2.0</a>
            <a href="/admin">Admin</a>
        </div>
    </header>

    <div class="hero">
        <h1>Global Timeline</h1>
        <p>Explore public updates across ActivityPub, Twtxt, RSS feeds, and local posts.</p>
    </div>

    <form class="search-bar" method="GET" action="/discover">
        <?php if ($currentNetwork !== ''): ?>
            <input type="hidden" name="network" value="<?= htmlspecialchars($currentNetwork) ?>">
        <?php endif; ?>
        <?php if ($currentTag !== ''): ?>
            <input type="hidden" name="tag" value="<?= htmlspecialchars($currentTag) ?>">
        <?php endif; ?>
        <input type="search" name="q" placeholder="Search global posts, authors, hashtags..." value="<?= htmlspecialchars($currentSearch) ?>">
        <button type="submit">Search</button>
    </form>

    <div class="filters-nav">
        <a href="<?= $buildUrl(['network' => null, 'offset' => null]) ?>" class="filter-btn <?= $currentNetwork === '' ? 'active' : '' ?>">
            All Networks <span class="filter-count">(<?= $total ?>)</span>
        </a>
        <a href="<?= $buildUrl(['network' => 'activitypub', 'offset' => null]) ?>" class="filter-btn <?= ($currentNetwork === 'activitypub' || $currentNetwork === 'ap') ? 'active' : '' ?>">
            ActivityPub <span class="filter-count">(<?= (int)($networks['activitypub'] ?? ($networks['ap'] ?? 0)) ?>)</span>
        </a>
        <a href="<?= $buildUrl(['network' => 'twtxt', 'offset' => null]) ?>" class="filter-btn <?= $currentNetwork === 'twtxt' ? 'active' : '' ?>">
            Twtxt <span class="filter-count">(<?= (int)($networks['twtxt'] ?? 0) ?>)</span>
        </a>
        <a href="<?= $buildUrl(['network' => 'rss', 'offset' => null]) ?>" class="filter-btn <?= $currentNetwork === 'rss' ? 'active' : '' ?>">
            RSS/Atom <span class="filter-count">(<?= (int)($networks['rss'] ?? 0) ?>)</span>
        </a>
        <a href="<?= $buildUrl(['network' => 'local', 'offset' => null]) ?>" class="filter-btn <?= $currentNetwork === 'local' ? 'active' : '' ?>">
            Local Posts <span class="filter-count">(<?= (int)($networks['local'] ?? 0) ?>)</span>
        </a>
    </div>

    <?php if ($currentTag !== ''): ?>
        <div class="active-tag-banner">
            <span>Filtering by hashtag: <strong>#<?= htmlspecialchars($currentTag) ?></strong></span>
            <a href="<?= $buildUrl(['tag' => null, 'offset' => null]) ?>" title="Clear tag filter">✕ Clear filter</a>
        </div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <div class="empty-state">
            <h3>No entries found</h3>
            <p style="margin-top: 8px;">No public federated or local posts match the current filter criteria.</p>
        </div>
    <?php else: ?>
        <div class="timeline">
            <?php foreach ($items as $item): ?>
                <?php
                $net = strtolower((string)($item['network'] ?? 'local'));
                $badgeInfo = $networkLabels[$net] ?? ['name' => ucfirst($net), 'class' => 'badge-local'];
                $author = $item['author'] ?? [];
                $authorName = htmlspecialchars((string)($author['name'] ?? 'Anonymous'));
                $authorUrl = htmlspecialchars((string)($author['url'] ?? '#'));
                $authorPhoto = htmlspecialchars((string)($author['photo'] ?? ''));
                $initial = mb_substr($authorName, 0, 1, 'UTF-8');
                $pubTs = (int)($item['published_ts'] ?? 0);
                $pubFormatted = $pubTs > 0 ? date('M j, Y H:i', $pubTs) : (string)($item['published'] ?? '');
                $cw = $item['content_warning'] ?? null;
                ?>
                <article class="entry-card">
                    <div class="entry-header">
                        <div class="author-info">
                            <div class="avatar">
                                <?php if ($authorPhoto !== ''): ?>
                                    <img src="<?= $authorPhoto ?>" alt="<?= $authorName ?>" loading="lazy">
                                <?php else: ?>
                                    <span><?= $initial ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="<?= $authorUrl ?>" class="author-name" target="_blank" rel="noopener"><?= $authorName ?></a>
                                <div class="entry-date"><?= $pubFormatted ?></div>
                            </div>
                        </div>
                        <div class="badges">
                            <span class="badge <?= $badgeInfo['class'] ?>"><?= $badgeInfo['name'] ?></span>
                        </div>
                    </div>

                    <?php if (!empty($item['title'])): ?>
                        <h2 class="entry-title"><?= htmlspecialchars((string)$item['title']) ?></h2>
                    <?php endif; ?>

                    <?php if ($cw !== null && $cw !== ''): ?>
                        <details class="cw-container">
                            <summary class="cw-summary">⚠️ CW: <?= htmlspecialchars($cw) ?> (Click to view)</summary>
                            <div class="entry-content" style="margin-top: 10px;">
                                <?= $item['content']['html'] ?? '' ?>
                            </div>
                        </details>
                    <?php else: ?>
                        <div class="entry-content">
                            <?= $item['content']['html'] ?? '' ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($item['photos']) && is_array($item['photos'])): ?>
                        <div class="gallery">
                            <?php foreach ($item['photos'] as $photo): ?>
                                <img src="<?= htmlspecialchars((string)$photo) ?>" alt="Attachment" loading="lazy">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($item['tags']) && is_array($item['tags'])): ?>
                        <div class="tags-list">
                            <?php foreach ($item['tags'] as $t): ?>
                                <?php $cleanTag = ltrim((string)$t, '#'); ?>
                                <a href="<?= $buildUrl(['tag' => $cleanTag, 'offset' => null]) ?>" class="tag-pill">#<?= htmlspecialchars($cleanTag) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="entry-footer">
                        <span><?= htmlspecialchars((string)($item['origin_server'] ?? 'Federated')) ?></span>
                        <?php if (!empty($item['url'])): ?>
                            <a href="<?= htmlspecialchars((string)$item['url']) ?>" target="_blank" rel="noopener">View Original ↗</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="pagination">
            <?php if ($offset > 0): ?>
                <?php $prevOffset = max(0, $offset - $limit); ?>
                <a href="<?= $buildUrl(['offset' => $prevOffset > 0 ? $prevOffset : null]) ?>">← Newer</a>
            <?php else: ?>
                <div></div>
            <?php endif; ?>

            <?php if ($offset + $limit < $total): ?>
                <?php $nextOffset = $offset + $limit; ?>
                <a href="<?= $buildUrl(['offset' => $nextOffset]) ?>">Older →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }
}
