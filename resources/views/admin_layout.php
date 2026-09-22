<?php

declare(strict_types=1);

/**
 * Legacy admin layout entry point - cleanly delegates to AdminLayoutView.
 * Estética: Lambe-Lambe Periférico, Dissidência Travesti & Resistência DIY.
 */
echo \Indieinabox\Views\Admin\AdminLayoutView::render(
    $content ?? '',
    $activeTab ?? 'microsub',
    $fqdn ?? ''
);
