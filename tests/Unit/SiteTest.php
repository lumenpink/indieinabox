<?php

declare(strict_types=1);

use Indieinabox\Site;
use Indieinabox\Site\Options;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Localization;
use Indieinabox\Site\Support;
use Indieinabox\Site\Metadata;

it(
    'Create Site class with default values',
    function () {
        $site = new Site();

        expect($site->options)->toMatchObject([
            'buildAll' => true,
            'dev' => false,
            'skipStatic' => false,
            'forceStaticOverride' => false
        ]);
        expect($site->paths)->toMatchObject([
            'baseDir' => '/',
            'outputDirHtml' => 'public_html',
            'outputDirGemini' => 'public_gemini',
            'outputDirGopher' => 'public_gopher',
            'outputDirMedia' => 'public_html/media',
            'contentDir' => 'content'
        ]);
        expect($site->localization)->toMatchObject([
            'lang' => ['en'],
            'defaultLang' => 'en'
        ]);
        expect($site->support)->toMatchObject([
            'support' => ['md', 'txt', 'html', 'htm'],
            'defaultCategory' => 'General'
        ]);
        expect($site->metadata)->toMatchObject([
            'title' => 'My Site',
            'sitename' => 'My Site',
            'author' => 'Me',
            'defaultTitle' => 'Untitled',
            'fqdn' => 'http://localhost:8080'
        ]);
    }
);

it(
    'Create Site class with custom values',
    function () {
        $site = new Site(
            new Metadata('title', 'description', 'keywords', 'NoTitle', 'http://example.com'),
            new Paths('/custom', 'custom_html', 'custom_gemini', 'custom_gopher', 'custom_media', 'custom_content'),
            new Options(false, true, true, true),
            new Localization(['en', 'fr'], 'pt'),
            new Support(['doc', 'xls'], 'None')
        );

        expect($site->options)->toMatchObject([
            'buildAll' => false,
            'dev' => true,
            'skipStatic' => true,
            'forceStaticOverride' => true
        ]);
        expect($site->paths)->toMatchObject([
            'baseDir' => '/custom',
            'outputDirHtml' => 'custom_html',
            'outputDirGemini' => 'custom_gemini',
            'outputDirGopher' => 'custom_gopher',
            'outputDirMedia' => 'custom_media',
            'contentDir' => 'custom_content'
        ]);
        expect($site->localization)->toMatchObject([
            'lang' => ['en', 'fr'],
            'defaultLang' => 'pt'
        ]);
        expect($site->support)->toMatchObject([
            'support' => ['doc', 'xls'],
            'defaultCategory' => 'None'
        ]);
        expect($site->metadata)->toMatchObject([
            'title' => 'title',
            'sitename' => 'description',
            'author' => 'keywords',
            'defaultTitle' => 'NoTitle',
            'fqdn' => 'http://example.com'
        ]);
    }
);
