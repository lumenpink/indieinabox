<?php if (!empty($page["kind"])) : ?>
    <h2 class="kind the-kind">
        <a href="<?= $page["relpath"] . $page["langpath"] . $page["localizedkind"] ?>">
            <img class="icon p-kind icon-gray" alt="<?= $page["localizedkind"] ?>" src="<?= $page["relpath"] . 'i/kind/' . $page["kind"] . ".svg" ?>">
        </a>
    </h2>
<?php endif; ?>