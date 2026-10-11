<?php

declare(strict_types=1);

namespace Tests\Unit\Markdown;

use Indieinabox\Markdown\ASTParser;
use Indieinabox\Markdown\HtmlRenderer;
use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Site\Site;

describe('Multilingual Wikilink Resolution in HtmlRenderer', function () {
    it('resolves wikilink in translated page to target language translated page', function () {
        $site = new Site();
        $site->localization->defaultLang = 'pt';
        $site->localization->lang = ['pt', 'en', 'es'];
        $site->config['urltranslations'] = [
            'curriculo' => [
                'en' => 'resume',
                'es' => 'curriculum-vitae',
            ],
            'travesti' => [
                'en' => 'travesti-gender-identity',
                'es' => 'travesti',
            ],
        ];

        // Original pages in pt
        $pageCurriculoPt = Page::fromArray([
            'slug' => 'curriculo/',
            'nick' => 'curriculo',
            'title' => 'Currículo',
            'lang' => 'pt',
            'kind' => 'generic',
            'relpath' => '../',
        ]);
        $pageTravestiPt = Page::fromArray([
            'slug' => 'jardins/travesti/',
            'nick' => 'travesti',
            'title' => 'Travesti',
            'lang' => 'pt',
            'kind' => 'garden',
            'relpath' => '../../',
        ]);

        // Translated pages in en
        $pageResumeEn = Page::fromArray([
            'slug' => 'en/resume/',
            'nick' => 'resume',
            'title' => 'Resume',
            'lang' => 'en',
            'kind' => 'generic',
            'relpath' => '../../',
        ]);
        $pageResumeEn->metadata->translation_source = 'curriculo/';
        $pageResumeEn->metadata->translation_source_lang = 'pt';

        $pageTravestiEn = Page::fromArray([
            'slug' => 'en/gardens/travesti-gender-identity/',
            'nick' => 'travesti-gender-identity',
            'title' => 'Travesti (Gender Identity)',
            'lang' => 'en',
            'kind' => 'garden',
            'relpath' => '../../../',
        ]);
        $pageTravestiEn->metadata->translation_source = 'jardins/travesti/';
        $pageTravestiEn->metadata->translation_source_lang = 'pt';

        // Source intro in pt and translated intro in en
        $introPt = Page::fromArray([
            'slug' => 'intro/',
            'nick' => 'intro',
            'title' => 'Intro',
            'lang' => 'pt',
            'kind' => 'generic',
            'relpath' => '../',
        ]);

        $introEn = Page::fromArray([
            'slug' => 'en/intro/',
            'nick' => 'intro',
            'title' => 'Introduction',
            'lang' => 'en',
            'kind' => 'generic',
            'relpath' => '../../',
        ]);
        $introEn->metadata->translation_source = 'intro/';
        $introEn->metadata->translation_source_lang = 'pt';

        $pages = new Pages([
            $pageCurriculoPt,
            $pageTravestiPt,
            $pageResumeEn,
            $pageTravestiEn,
            $introPt,
            $introEn,
        ]);

        $parser = new ASTParser();
        $ast = $parser->parse('Veja meu [[currículo|aqui]] e sobre [[Travesti]].');

        $renderer = new HtmlRenderer($introEn, $site, $pages);
        $html = $renderer->render($ast);

        // Expect links to resolve to en/resume/ and en/gardens/travesti-gender-identity/
        expect($html)->toContain('href="../../en/resume/"');
        expect($html)->toContain('href="../../en/gardens/travesti-gender-identity/"');
        expect($html)->not->toContain('href="../../curriculo/"');
        expect($html)->not->toContain('href="../../jardins/travesti/"');
    });
});
