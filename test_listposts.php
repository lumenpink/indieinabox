<?php
require 'vendor/autoload.php';
require 'app/Database.php';
\Indieinabox\Database::connect('/home/lumen/jardim/data/.indieinabox.sqlite');
$site = new \Indieinabox\Site(
    new \Indieinabox\Site\Metadata(),
    new \Indieinabox\Site\Paths(),
    new \Indieinabox\Site\Options(),
    new \Indieinabox\Site\Localization(),
    new \Indieinabox\Site\Support(),
    new \Indieinabox\Site\Twtxt()
);
$site->config = \Indieinabox\Database::getAllSettings();
$site->config['kinds'] = \Indieinabox\Database::getKinds();
$site->localization->defaultLang = 'pt';
$site->localization->lang = ['pt'];
$site->paths->contentDir = __DIR__ . '/content';
var_dump(\Indieinabox\Helper::getKindConfig('article'));
