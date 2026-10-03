<?php
/** @var \Indieinabox\Page\Page $page */
/** @var \Indieinabox\Site\Site $site */
global $langLinks, $headerLinks;
use Indieinabox\Theme\ThemeData;
?>
<header>
    <div class="terminal-bar" style="font-size: 0.8em; color: var(--accent-cyan); margin-bottom: 0.5em;">
        [TERMINAL_ONLINE] // NODE: <?= htmlspecialchars($site->metadata->sitename ?? 'INDIEINABOX') ?>
    </div>
    <pre class="logo-figlet">       _                            
      | |_   _ _ __ ___   ___ _ __  
 /\/| | | | | | '_ ` _ \ / _ \ '_ \ 
|/\/  | | |_| | | | | | |  __/ | | |
      |_|\__,_|_| |_| |_|\___|_| |_|</pre>
    
    <?= ThemeData::getLanguageSelector($page, $site, $langLinks ?? null) ?>
    <?= ThemeData::getHeaderNavLinks($page, $site, $headerLinks ?? []) ?>
</header>
