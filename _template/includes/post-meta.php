<div class="post-meta">
    <div>
        <!-- //TODO: Add a short-permalink to the post (probably using picopub)-->
        <a class=" permalink u-url" href="<?= $page["relpath"] . $page["slug"] ?>">
            <img alt="permalink" class="icon-24" src="/i/permalink.svg" />
            <?php if (isset($page["date"])) : ?>
                <time
                    class="dt-published"
                    datetime="<?= $page["isodate"] ?>">
                    <?= $page["localizeddate"] ?>
                </time>
            <?php endif; ?>
        </a>
    </div>
    <div>
        <?php if (isset($site->author) && (isset($page["noauthor"]) && $page["noauthor"] !== true)) : ?>
            <span class="p-author h-card"><a href="<?= $site->fqdn ?>" class="u-id u-url" rel="author"><img
                        alt="<?= $site->sitename ?>"
                        class="u-photo icon-24 icon-round p-given-name"
                        src="<?= $page["relpath"] ?>/images/thumb250.jpg" /><span class="post-meta p-name"><?= $site->author ?></span></a></span>
        <?php endif; ?>
    </div>
    <div>
        <?php if (isset($page["tags"]) && !empty($page["tags"])) : ?>
            <img alt="tag" class="icon-24" src="<?= $page["relpath"] ?>i/tag.svg" />
            <?php foreach ($page["tags"] as $tag) : ?>
                <span class="post-meta">
                    <a href="<?= $page["relpath"] . $page["langpath"] . "tag/" . $tag ?>" class="u-tag">
                        <?= $tag ?>
                    </a>
                </span>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <!-- TODO: add syndication links -->
</div>