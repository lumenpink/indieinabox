<?php

declare(strict_types=1);

use Indieinabox\Helper;
use Indieinabox\Page;
use Indieinabox\Pages;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\SiteBuilder;
use Indieinabox\SiteBuilder\FeedPublisher;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_feed_pub_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);

    $paths = new Paths(
        $this->tempDir,
        'public_html',
        'public_gemini',
        'public_gopher',
        'public_media',
        'content',
        'resources'
    );
    $this->site = new Site(null, $paths);
    $this->site->metadata->sitename = 'Feed Pub Blog';
    $this->site->metadata->description = 'A blog for testing FeedPublisher';
    $this->site->metadata->fqdn = 'https://example.com';
    $this->site->localization->lang = ['en', 'pt'];
    $this->site->localization->defaultLang = 'en';

    $this->publisher = new FeedPublisher($this->site);
});

afterEach(function () {
    Helper::recursiveRmdir($this->tempDir);
});

it('publishes RSS, Atom and Twtxt feeds for default and other languages', function () {
    $pages = new Pages();

    $enPage = Page::fromArray([
        'title' => 'English Post',
        'slug' => 'english-post',
        'content' => '<p>English Content</p>',
        'lang' => 'en',
        'date' => time(),
        'kind' => 'article',
    ]);

    $ptPage = Page::fromArray([
        'title' => 'Post em Português',
        'slug' => 'post-portugues',
        'content' => '<p>Conteúdo em Português</p>',
        'lang' => 'pt',
        'date' => time(),
        'kind' => 'article',
    ]);

    $pages->add($enPage);
    $pages->add($ptPage);

    $this->publisher->publishFeeds($pages);

    // Default language feeds (in public_html root)
    $rssDefault = $this->tempDir . '/public_html/rss.xml';
    $atomDefault = $this->tempDir . '/public_html/atom.xml';
    $twtxtDefault = $this->tempDir . '/public_html/twtxt.txt';

    expect(file_exists($rssDefault))->toBeTrue();
    expect(file_exists($atomDefault))->toBeTrue();
    expect(file_exists($twtxtDefault))->toBeTrue();

    // Twtxt replicated to Gemini and Gopher
    $geminiTwtxt = $this->tempDir . '/public_gemini/twtxt.txt';
    $gopherTwtxt = $this->tempDir . '/public_gopher/twtxt.txt';
    expect(file_exists($geminiTwtxt))->toBeTrue();
    expect(file_exists($gopherTwtxt))->toBeTrue();

    // Portuguese feeds (in public_html/pt)
    $rssPt = $this->tempDir . '/public_html/pt/rss.xml';
    $atomPt = $this->tempDir . '/public_html/pt/atom.xml';
    $twtxtPt = $this->tempDir . '/public_html/pt/twtxt.txt';

    expect(file_exists($rssPt))->toBeTrue();
    expect(file_exists($atomPt))->toBeTrue();
    expect(file_exists($twtxtPt))->toBeTrue();

    expect(file_get_contents($rssDefault))->toContain('English Post');
    expect(file_get_contents($rssPt))->toContain('Post em Português');

    // Manifest should contain generated files
    expect(isset(SiteBuilder::$manifest[$rssDefault]))->toBeTrue();
    expect(isset(SiteBuilder::$manifest[$atomDefault]))->toBeTrue();
    expect(isset(SiteBuilder::$manifest[$twtxtDefault]))->toBeTrue();
});
