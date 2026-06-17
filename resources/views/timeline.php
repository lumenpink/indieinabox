<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
/** @var array $timeline */
/** @var array $mentions */
?>
<!DOCTYPE html>
<html lang="<?= $page->lang ?>">

<head>
    <?php include 'includes/head.php'; //NOSONAR ?>
    <style>
        .timeline-container {
            max-width: 650px;
            margin: 2rem auto;
            padding: 0 1rem;
            font-family: 'Inter', system-ui, sans-serif;
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }

        .timeline-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: #fff;
            margin: 0;
        }

        .timeline-nav {
            display: flex;
            gap: 1rem;
        }

        .timeline-tab {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            color: #ccc;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .timeline-tab:hover, .timeline-tab.active {
            background: #ff5e97;
            color: #fff;
            border-color: #ff5e97;
            box-shadow: 0 0 15px rgba(255, 94, 151, 0.4);
        }

        .timeline-stream {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .twtxt-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .twtxt-card:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 94, 151, 0.2);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        }

        .twtxt-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.8rem;
            font-size: 0.85rem;
        }

        .twtxt-author {
            font-weight: 700;
            color: #ff5e97;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .twtxt-time {
            color: #777;
        }

        .twtxt-body {
            font-size: 1rem;
            line-height: 1.6;
            color: #ddd;
            word-break: break-word;
        }

        .twtxt-body a {
            color: #ff5e97;
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px dashed rgba(255, 94, 151, 0.3);
            transition: color 0.2s ease;
        }

        .twtxt-body a:hover {
            color: #ff85b2;
            border-bottom-style: solid;
        }

        .twtxt-empty {
            text-align: center;
            padding: 3rem;
            color: #666;
            font-style: italic;
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; //NOSONAR ?>

    <div class="timeline-container">
        <div class="timeline-header">
            <h1 class="timeline-title">Microblog</h1>
            <div class="timeline-nav">
                <button class="timeline-tab active" onclick="switchTab('timeline-feed', this)">Timeline</button>
                <button class="timeline-tab" onclick="switchTab('timeline-mentions', this)">Mentions</button>
            </div>
        </div>

        <div id="timeline-feed" class="timeline-section">
            <div class="timeline-stream">
                <?php if (empty($timeline)): ?>
                    <div class="twtxt-empty">No updates from followed feeds yet.</div>
                <?php else: ?>
                    <?php foreach ($timeline as $entry): ?>
                        <div class="twtxt-card">
                            <div class="twtxt-meta">
                                <span class="twtxt-author">@<?= htmlspecialchars($entry->nick) ?></span>
                                <span class="twtxt-time"><?= $entry->timestamp->format('M j, Y - H:i') ?></span>
                            </div>
                            <div class="twtxt-body">
                                <?= $entry->html ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div id="timeline-mentions" class="timeline-section" style="display: none;">
            <div class="timeline-stream">
                <?php if (empty($mentions)): ?>
                    <div class="twtxt-empty">No mentions or replies found.</div>
                <?php else: ?>
                    <?php foreach ($mentions as $entry): ?>
                        <div class="twtxt-card">
                            <div class="twtxt-meta">
                                <span class="twtxt-author">@<?= htmlspecialchars($entry->nick) ?></span>
                                <span class="twtxt-time"><?= $entry->timestamp->format('M j, Y - H:i') ?></span>
                            </div>
                            <div class="twtxt-body">
                                <?= $entry->html ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function switchTab(sectionId, btn) {
            document.querySelectorAll('.timeline-section').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.timeline-tab').forEach(el => el.classList.remove('active'));
            document.getElementById(sectionId).style.display = 'block';
            btn.classList.add('active');
        }
    </script>

    <?php include 'includes/footer.php'; //NOSONAR ?>
</body>

</html>
