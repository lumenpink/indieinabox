<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Page $p */
/** @var \Indieinabox\Site $site */
/** @var array $kinds */
?>
    <header class="header">
        <a href="<?= $page->relpath ?>" class="logo">
            <img src="<?= $page->relpath ?>apple-touch-icon-72x72.png" alt="Site Logo" />
            ~lumen</a>
        <input class="menu-btn" type="checkbox" id="menu-btn" />
        <label class="menu-icon" for="menu-btn"><span class="navicon"></span></label>

        <ul class="menu-big">
            <li>

                <a href="<?= $page->relpath . $page->langpath . ts("pensamentos") ?>">

                    <span><?= t("Pensamentos") ?></span>
                </a>
            </li>

            <li>
                <a href="<?= $page->relpath . $page->langpath . ts("agora") ?>">

                    <span><?= t("Agora") ?></span>
                </a>
            </li>

            <li>
                <a class="upper-flag" href="<?= $page->relpath . $p->otherlangpath[0] ?>"><img src="<?= $page->relpath ?>flags/<?= $p->otherlang[0] ?>.gif" alt='<?= t("Conteúdo em Português", $p->otherlang[0]) ?>'></a>
                <a class="bottom-flag" href="<?= $page->relpath . $p->otherlangpath[1] ?>"><img src="<?= $page->relpath ?>flags/<?= $p->otherlang[1] ?>.gif" alt="<?= t("Conteúdo em Português", $p->otherlang[1]) ?>"></a>
            </li>
        </ul>
        <ul class="menu">
            <li class="menu-item-small menu-box">
                <a href="<?= $page->relpath ?>agora/">
                    <span>Agora</span>
                </a>
            </li>
            <li class="menu-item-small">
                <a class="upper-flag" href="<?= $page->relpath . $p->otherlangpath[0] ?>"><img src="<?= $page->relpath ?>flags/<?= $p->otherlang[0] ?>.gif " alt='<?= t("Conteúdo em Português", $p->otherlang[0]) ?>'></a>
                <a class="bottom-flag" href="<?= $page->relpath . $p->otherlangpath[1] ?>"><img src="<?= $page->relpath ?>flags/<?= $p->otherlang[1] ?>.gif" alt="<?= t("Conteúdo em Português", $p->otherlang[1]) ?>"></a>
            </li>

            <?php foreach ($kinds as $kind => $icon) : ?>
                <li class="menu-kind">
                    <a href="<?= $page->relpath . $page->langpath . ts($kind) ?>">
                        <img class="icon p-kind" alt="<?= t($kind) ?>"
                            src="<?= $page->relpath . 'i/' . $icon . ".png" ?>">
                    </a>
                </li>
            <?php endforeach; ?>


        </ul>

        <div>

        </div>

    </header>