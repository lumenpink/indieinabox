<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
?>
<article class="h-entry the-summary">
    <div class="summary summary-<?= $page->kind ?>">
        <?php if ($page->kind !== "page") : ?>
            <div class="summary-kind">
                <?php include('kind.php'); ?>
            </div>
        <?php endif ?>
        <div class="fullwidth">
            <div class="e-content summary-content summary-kind-<?= $page->kind ?>">
                <?php if (isset($page->images) && is_array($page->images)) :
                    foreach ($page->images as $image) :
                        if (!isset($image["url"])) {
                            continue;
                        }
                        if (!isset($image["alt"])) {
                            $image["alt"] = t("Imagem criada por ") . $site->author;
                        }
                ?>

                        <div class="text-center">
                            <a href="<?= $page->relpath . $image["url"] ?>" class="u-photo" rel="nofollow">
                                <img src="<?= $page->relpath . $image["url"] ?>" alt="<?= $image["alt"] ?>" class="width-75">
                            </a>
                        </div>
                <?php
                    endforeach;
                endif;
                ?>
                <?= $page->content; ?>
            </div>
            <div>
                <?php include("post-meta.php") ?>
                <!-- Add webmentions -->
                <?php include(__DIR__ . '/../partials/webmentions.php'); ?>
            </div>
        </div>
    </div>
</article>