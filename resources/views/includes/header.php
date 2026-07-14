<?php
/** @var \Indieinabox\Page $page */
/** @var \Indieinabox\Site $site */
global $langLinks, $headerLinks;
use Indieinabox\Theme\ThemeData;
?>
<header>
    <pre class="logo-figlet">       _                            
      | |_   _ _ __ ___   ___ _ __  
 /\/| | | | | | '_ ` _ \ / _ \ '_ \ 
|/\/  | | |_| | | | | | |  __/ | | |
      |_|\__,_|_| |_| |_|\___|_| |_|</pre>
    
    <?= ThemeData::getLanguageSelector($page, $site, $langLinks ?? null) ?>
    <?= ThemeData::getHeaderNavLinks($page, $site, $headerLinks ?? []) ?>
    
    <hr>
</header>
