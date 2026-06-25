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
                    foreach ($page->images as $imgData) :
                        $img = (array) $imgData;
                        if (!isset($img["url"])) {
                            continue;
                        }
                        if (!isset($img["alt"])) {
                            $img["alt"] = t("Criada por ") . $site->author;
                        }
                ?>

                        <div class="text-center">
                            <a href="<?= $page->relpath . $img["url"] ?>" class="u-photo" rel="nofollow">
                                <img src="<?= $page->relpath . $img["url"] ?>"
                                     alt="<?= $img["alt"] ?>"
                                     class="width-75">
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
