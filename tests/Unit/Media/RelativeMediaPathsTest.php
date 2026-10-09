<?php

declare(strict_types=1);

use Indieinabox\Markdown\ASTParser;
use Indieinabox\Markdown\HtmlRenderer;
use Indieinabox\Media\AttachmentResolver;
use Indieinabox\Page\Page;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\SiteBuilder;

beforeEach(function () {
    AttachmentResolver::reset();
    SiteBuilder::$manifest = [];
});

test('renders absolute media path when relpath is not set on page', function () {
    $tempApp = sys_get_temp_dir() . '/iiab_test_rel_media_' . uniqid();
    $contentDir = $tempApp . '/content';
    mkdir($contentDir, 0777, true);
    mkdir($tempApp . '/public_media', 0777, true);
    mkdir($tempApp . '/public_html', 0777, true);

    $sampleImg = $contentDir . '/sample.png';
    $img = imagecreatetruecolor(100, 100);
    imagepng($img, $sampleImg);
    imagedestroy($img);

    $site = new Site(null, new Paths(
        baseDir: $tempApp,
        outputDirHtml: 'public_html',
        outputDirMedia: 'public_media',
        contentDir: $contentDir
    ));

    $page = new Page();
    $page->filepath = $contentDir . '/root.md';
    $page->slug = 'root.html';

    $parser = new ASTParser();
    $ast = $parser->parse('![[sample.png|100x100|Sample Root]]');

    $renderer = new HtmlRenderer($page, $site);
    $html = $renderer->render($ast);

    expect($html)->toContain('<img src="/media/sample_100.gif"')
        ->and($html)->toContain('<a href="/media/sample.png"')
        ->and($html)->not->toContain('<img src="./media');
});

test('prefixes relative path when relpath is set for nested pages', function () {
    $tempApp = sys_get_temp_dir() . '/iiab_test_nested_media_' . uniqid();
    $contentDir = $tempApp . '/content';
    mkdir($contentDir, 0777, true);
    mkdir($tempApp . '/public_media', 0777, true);
    mkdir($tempApp . '/public_html', 0777, true);

    $sampleImg = $contentDir . '/nested.png';
    $img = imagecreatetruecolor(100, 100);
    imagepng($img, $sampleImg);
    imagedestroy($img);

    $site = new Site(null, new Paths(
        baseDir: $tempApp,
        outputDirHtml: 'public_html',
        outputDirMedia: 'public_media',
        contentDir: $contentDir
    ));

    $page = new Page();
    $page->filepath = $contentDir . '/posts/deep.md';
    $page->slug = 'posts/deep.html';
    $page->relpath = '../../';

    $parser = new ASTParser();
    $ast = $parser->parse('![[nested.png|100x100|Nested Sample]]');

    $renderer = new HtmlRenderer($page, $site);
    $html = $renderer->render($ast);

    expect($html)->toContain('<img src="../../media/nested_100.gif"')
        ->and($html)->toContain('<a href="../../media/nested.png"');
});
