<?php
/** @var \Indieinabox\Page\Page $page */
/** @var \Indieinabox\Site\Site $site */
use Indieinabox\Theme\ThemeData;
$activeLangLinks = $langLinks ?? ($GLOBALS['langLinks'] ?? null);
$activeHeaderLinks = $headerLinks ?? ($GLOBALS['headerLinks'] ?? []);
?>
<header>
    <pre class="logo-figlet">       _                            
      | |_   _ _ __ ___   ___ _ __  
 /\/| | | | | | '_ ` _ \ / _ \ '_ \ 
|/\/  | | |_| | | | | | |  __/ | | |
      |_|\__,_|_| |_| |_|\___|_| |_|</pre>
    
    <?= ThemeData::getLanguageSelector($page, $site, $activeLangLinks) ?>
    <?= ThemeData::getHeaderNavLinks($page, $site, $activeHeaderLinks) ?>
    
    <hr>
</header>
