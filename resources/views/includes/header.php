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
                <?php foreach ($p->otherlang as $i => $otherLang) : ?>
                    <?php if (isset($p->otherlangpath[$i])) : ?>
                        <a class="<?= $i === 0 ? 'upper-flag' : 'bottom-flag' ?>" href="<?= $page->relpath . $p->otherlangpath[$i] ?>">
                            <img src="<?= $page->relpath ?>flags/<?= $otherLang ?>.gif" alt='<?= t("Conteúdo em Português", $otherLang) ?>'>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </li>
        </ul>
        <ul class="menu">
            <li class="menu-item-small menu-box">
                <a href="<?= $page->relpath ?>agora/">
                    <span>Agora</span>
                </a>
            </li>
            <li class="menu-item-small">
                <?php foreach ($p->otherlang as $i => $otherLang) : ?>
                    <?php if (isset($p->otherlangpath[$i])) : ?>
                        <a class="<?= $i === 0 ? 'upper-flag' : 'bottom-flag' ?>" href="<?= $page->relpath . $p->otherlangpath[$i] ?>">
                            <img src="<?= $page->relpath ?>flags/<?= $otherLang ?>.gif" alt='<?= t("Conteúdo em Português", $otherLang) ?>'>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
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