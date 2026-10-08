<?php

declare(strict_types=1);

use Indieinabox\Markdown\ASTParser;
use Indieinabox\Markdown\GemtextRenderer;
use Indieinabox\Markdown\GophermapRenderer;
use Indieinabox\Markdown\HtmlRenderer;
use Indieinabox\Markdown\ImageNode;
use Indieinabox\Media\AttachmentResolver;
use Indieinabox\Page\Page;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\SiteBuilder;

beforeEach(function () {
    AttachmentResolver::reset();
    SiteBuilder::$manifest = [];
});

it('parses image wikilinks with dimensions and labels correctly in ASTParser', function () {
    $parser = new ASTParser();

    // 1. Simple wikilink: ![[image.jpg]]
    $ast1 = $parser->parse('![[image.jpg]]');
    $img1 = $ast1->children[0]->children[0];
    expect($img1)->toBeInstanceOf(ImageNode::class)
        ->and($img1->target)->toBe('image.jpg')
        ->and($img1->label)->toBe('image.jpg')
        ->and($img1->width)->toBeNull();

    // 2. Wikilink with width: ![[image.jpg|600]]
    $ast2 = $parser->parse('![[image.jpg|600]]');
    $img2 = $ast2->children[0]->children[0];
    expect($img2)->toBeInstanceOf(ImageNode::class)
        ->and($img2->target)->toBe('image.jpg')
        ->and($img2->label)->toBe('image.jpg')
        ->and($img2->width)->toBe('600')
        ->and($img2->height)->toBeNull();

    // 3. Wikilink with dimensions: ![[image.jpg|800x600]]
    $ast3 = $parser->parse('![[image.jpg|800x600]]');
    $img3 = $ast3->children[0]->children[0];
    expect($img3)->toBeInstanceOf(ImageNode::class)
        ->and($img3->target)->toBe('image.jpg')
        ->and($img3->width)->toBe('800')
        ->and($img3->height)->toBe('600');

    // 4. Wikilink with custom alt label: ![[image.jpg|A photo of something]]
    $ast4 = $parser->parse('![[image.jpg|A photo of something]]');
    $img4 = $ast4->children[0]->children[0];
    expect($img4)->toBeInstanceOf(ImageNode::class)
        ->and($img4->target)->toBe('image.jpg')
        ->and($img4->label)->toBe('A photo of something')
        ->and($img4->width)->toBeNull();

    // 5. Wikilink with width and alt: ![[image.jpg|400|Custom Caption]]
    $ast5 = $parser->parse('![[image.jpg|400|Custom Caption]]');
    $img5 = $ast5->children[0]->children[0];
    expect($img5)->toBeInstanceOf(ImageNode::class)
        ->and($img5->target)->toBe('image.jpg')
        ->and($img5->label)->toBe('Custom Caption')
        ->and($img5->width)->toBe('400');
});

it('resolves attachments from 99_anexo and configured obsidian folder', function () {
    $tempVault = sys_get_temp_dir() . '/test_vault_' . uniqid();
    $notesDir = $tempVault . '/97_floranda/notes';
    $anexoDir = $tempVault . '/99_anexo';
    $obsidianDir = $tempVault . '/.obsidian';

    @mkdir($notesDir, 0777, true);
    @mkdir($anexoDir, 0777, true);
    @mkdir($obsidianDir, 0777, true);

    file_put_contents($obsidianDir . '/app.json', json_encode(['attachmentFolderPath' => '99_anexo']));
    file_put_contents($anexoDir . '/test_photo.jpg', 'dummy image content');

    $site = new Site(null, new Paths(
        baseDir: $tempVault,
        outputDirHtml: 'public_html',
        outputDirGemini: 'public_gemini',
        outputDirGopher: 'public_gopher',
        outputDirMedia: 'public_media',
        contentDir: $tempVault . '/97_floranda'
    ));

    $notePath = $notesDir . '/my_note.md';
    $resolved = AttachmentResolver::resolve('test_photo.jpg', $notePath, $site);

    expect($resolved)->not->toBeNull()
        ->and(basename((string)$resolved))->toBe('test_photo.jpg')
        ->and(file_exists((string)$resolved))->toBeTrue();

    // Clean up
    @unlink($anexoDir . '/test_photo.jpg');
    @unlink($obsidianDir . '/app.json');
    @rmdir($obsidianDir);
    @rmdir($anexoDir);
    @rmdir($notesDir);
    @rmdir($tempVault . '/97_floranda');
    @rmdir($tempVault);
});

it('resolves duplicate filenames using path proximity ranking', function () {
    $tempVault = sys_get_temp_dir() . '/test_vault_prox_' . uniqid();
    $folderA = $tempVault . '/97_floranda/section_a';
    $folderB = $tempVault . '/97_floranda/section_b';
    @mkdir($folderA, 0777, true);
    @mkdir($folderB, 0777, true);

    file_put_contents($folderA . '/duplicate.png', 'content A');
    file_put_contents($folderB . '/duplicate.png', 'content B');

    $site = new Site(null, new Paths(
        baseDir: $tempVault,
        outputDirHtml: 'public_html',
        contentDir: $tempVault . '/97_floranda'
    ));

    $noteA = $folderA . '/note_a.md';
    $resolvedA = AttachmentResolver::resolve('duplicate.png', $noteA, $site);
    expect($resolvedA)->toBe(realpath($folderA . '/duplicate.png'));

    $noteB = $folderB . '/note_b.md';
    $resolvedB = AttachmentResolver::resolve('duplicate.png', $noteB, $site);
    expect($resolvedB)->toBe(realpath($folderB . '/duplicate.png'));

    // Clean up
    @unlink($folderA . '/duplicate.png');
    @unlink($folderB . '/duplicate.png');
    @rmdir($folderA);
    @rmdir($folderB);
    @rmdir($tempVault . '/97_floranda');
    @rmdir($tempVault);
});

it('renders ImageNode with dimensions, /media/ path, and mirrors across protocols', function () {
    $tempApp = sys_get_temp_dir() . '/test_app_media_' . uniqid();
    $contentDir = $tempApp . '/content';
    $anexoDir = $tempApp . '/99_anexo';
    @mkdir($contentDir, 0777, true);
    @mkdir($anexoDir, 0777, true);

    // Create a 10x10 genuine test PNG
    $img = imagecreatetruecolor(10, 10);
    $testImgPath = $anexoDir . '/sample.png';
    imagepng($img, $testImgPath);

    $site = new Site(null, new Paths(
        baseDir: $tempApp,
        outputDirHtml: 'public_html',
        outputDirGemini: 'public_gemini',
        outputDirGopher: 'public_gopher',
        outputDirMedia: 'public_media',
        contentDir: $contentDir
    ));

    $page = new Page();
    $page->filepath = $contentDir . '/test.md';
    $page->slug = 'notes/test.html';

    $parser = new ASTParser();
    $ast = $parser->parse('![[sample.png|500x300|Alt text for sample]]');

    $renderer = new HtmlRenderer($page, $site);
    $html = $renderer->render($ast);

    expect($html)->toContain('<figure class="content-media"')
        ->and($html)->toContain('<img src="/media/sample_500.gif" alt="Alt text for sample" width="500" height="300"')
        ->and($html)->toContain('<a href="/media/sample.png" class="dithered-image-link" target="_self"')
        ->and($html)->not->toContain('target="_blank"')
        ->and(file_exists($tempApp . '/public_media/sample_500.gif'))->toBeTrue()
        ->and(file_exists($tempApp . '/public_media/sample.png'))->toBeTrue()
        ->and(file_exists($tempApp . '/public_html/media/sample_500.gif'))->toBeTrue()
        ->and(file_exists($tempApp . '/public_gemini/media/sample_500.gif'))->toBeTrue()
        ->and(file_exists($tempApp . '/public_gopher/media/sample_500.gif'))->toBeTrue();

    // Gemtext rendering check
    $gemtextRenderer = new GemtextRenderer($page);
    $gmi = $gemtextRenderer->render($ast);
    expect($gmi)->toContain('=> /media/sample.gif [Foto: Alt text for sample]');

    // Gophermap rendering check
    $gopherRenderer = new GophermapRenderer('localhost', 70, $page);
    $gph = $gopherRenderer->render($ast);
    expect($gph)->toContain('/media/sample.gif');

    // Clean up
    $cleanDirs = [
        $tempApp . '/public_media',
        $tempApp . '/public_html/media',
        $tempApp . '/public_html',
        $tempApp . '/public_gemini/media',
        $tempApp . '/public_gemini',
        $tempApp . '/public_gopher/media',
        $tempApp . '/public_gopher',
        $contentDir,
        $anexoDir,
    ];
    @unlink($tempApp . '/public_media/sample_500.gif');
    @unlink($tempApp . '/public_media/sample_500_global.gif');
    @unlink($tempApp . '/public_media/sample_thumb.gif');
    @unlink($tempApp . '/public_media/sample.png');
    @unlink($tempApp . '/public_html/media/sample_500.gif');
    @unlink($tempApp . '/public_html/media/sample_500_global.gif');
    @unlink($tempApp . '/public_html/media/sample_thumb.gif');
    @unlink($tempApp . '/public_html/media/sample.png');
    @unlink($tempApp . '/public_gemini/media/sample_500.gif');
    @unlink($tempApp . '/public_gemini/media/sample_500_global.gif');
    @unlink($tempApp . '/public_gemini/media/sample_thumb.gif');
    @unlink($tempApp . '/public_gemini/media/sample.png');
    @unlink($tempApp . '/public_gopher/media/sample_500.gif');
    @unlink($tempApp . '/public_gopher/media/sample_500_global.gif');
    @unlink($tempApp . '/public_gopher/media/sample_thumb.gif');
    @unlink($tempApp . '/public_gopher/media/sample.png');
    @unlink($testImgPath);

    foreach ($cleanDirs as $dir) {
        @rmdir($dir);
    }
    @rmdir($tempApp);
});
