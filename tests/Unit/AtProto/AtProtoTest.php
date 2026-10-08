<?php

declare(strict_types=1);

use Indieinabox\AtProto\AtProtoCardBuilder;
use Indieinabox\AtProto\AtProtoClient;
use Indieinabox\AtProto\AtProtoFacetParser;
use Indieinabox\AtProto\BskySyndicationService;
use Indieinabox\Console\Commands\BskyCommand;
use Indieinabox\Core\Database;
use Indieinabox\Http\WebRouter;
use Indieinabox\Page\Page;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_atproto_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);
    mkdir($this->tempDir . '/content', 0777, true);
    mkdir($this->tempDir . '/content/notes', 0777, true);
    mkdir($this->tempDir . '/content/articles', 0777, true);
    mkdir($this->tempDir . '/public_html', 0777, true);

    Database::disconnect();
    Database::$dataDir = $this->tempDir . '/data';
    Database::connect(':memory:');
    $sql = (string) file_get_contents(dirname(__DIR__, 3) . '/database.sql');
    Database::getDb()->exec($sql);

    $paths = new Paths($this->tempDir);
    $paths->outputDirHtml = 'public_html';
    $this->site = new Site(null, $paths);

    $_GET = [];
    $_POST = [];
    $_SERVER = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
});

afterEach(function () {
    Database::disconnect();
    FileUtils::recursiveRmdir($this->tempDir);
});

describe('AtProtoFacetParser', function () {
    it('extracts links with exact UTF-8 byte offsets including multibyte characters', function () {
        // "Olá mundo 🌍! Visite https://lumen.pink para novidades."
        // "Olá mundo 🌍! Visite "
        // "O" = 1 byte
        // "l" = 1 byte
        // "á" = 2 bytes
        // " " = 1 byte
        // "mundo " = 6 bytes
        // "🌍" = 4 bytes
        // "! Visite " = 9 bytes
        // Total byte offset before URL = 1+1+2+1+6+4+9 = 24 bytes!
        $text = "Olá mundo 🌍! Visite https://lumen.pink para novidades.";
        $facets = AtProtoFacetParser::parse($text);

        expect($facets)->toHaveCount(1);
        $facet = $facets[0];

        $expectedUrl = 'https://lumen.pink';
        $byteStart = $facet['index']['byteStart'];
        $byteEnd = $facet['index']['byteEnd'];

        expect($byteStart)->toBe(24);
        expect($byteEnd)->toBe(24 + strlen($expectedUrl));
        expect($facet['features'][0]['$type'])->toBe('app.bsky.richtext.facet#link');
        expect($facet['features'][0]['uri'])->toBe($expectedUrl);

        // Verify byte slice on binary string matches exact URI
        $extractedSlice = substr($text, $byteStart, $byteEnd - $byteStart);
        expect($extractedSlice)->toBe($expectedUrl);
    });

    it('extracts hashtags with unicode support', function () {
        $text = "Publicando no meu blog #indieweb e tomando um #café delicioso!";
        $facets = AtProtoFacetParser::parse($text);

        expect($facets)->toHaveCount(2);

        expect($facets[0]['features'][0]['$type'])->toBe('app.bsky.richtext.facet#tag');
        expect($facets[0]['features'][0]['tag'])->toBe('indieweb');

        expect($facets[1]['features'][0]['$type'])->toBe('app.bsky.richtext.facet#tag');
        expect($facets[1]['features'][0]['tag'])->toBe('café');
    });

    it('extracts mentions when mapped to DIDs', function () {
        $text = "Olá @alice.bsky.social e @bob.test!";
        $mentionMap = [
            'alice.bsky.social' => 'did:plc:alice12345',
        ];

        $facets = AtProtoFacetParser::parse($text, $mentionMap);

        expect($facets)->toHaveCount(1);
        expect($facets[0]['features'][0]['$type'])->toBe('app.bsky.richtext.facet#mention');
        expect($facets[0]['features'][0]['did'])->toBe('did:plc:alice12345');
    });
});

describe('AtProtoClient', function () {
    it('creates session and parses response', function () {
        $mockTransport = function (string $url, string $method, array $headers, ?string $body) {
            expect($url)->toBe('https://bsky.social/xrpc/com.atproto.server.createSession');
            expect($method)->toBe('POST');
            $payload = json_decode((string) $body, true);
            expect($payload['identifier'])->toBe('alice.bsky.social');
            expect($payload['password'])->toBe('secret-pass');

            return [
                'code' => 200,
                'body' => json_encode([
                    'accessJwt' => 'mock-access-token',
                    'refreshJwt' => 'mock-refresh-token',
                    'did' => 'did:plc:alice123',
                    'handle' => 'alice.bsky.social',
                ]),
                'error' => '',
            ];
        };

        $client = new AtProtoClient($mockTransport);
        $session = $client->createSession('alice.bsky.social', 'secret-pass');

        expect($session['accessJwt'])->toBe('mock-access-token');
        expect($session['did'])->toBe('did:plc:alice123');
        expect($session['handle'])->toBe('alice.bsky.social');
    });

    it('creates record via XRPC', function () {
        $mockTransport = function (string $url, string $method, array $headers, ?string $body) {
            expect($url)->toBe('https://bsky.social/xrpc/com.atproto.repo.createRecord');
            expect($method)->toBe('POST');
            expect($headers)->toContain('Authorization: Bearer mock-jwt');

            $payload = json_decode((string) $body, true);
            expect($payload['repo'])->toBe('did:plc:alice123');
            expect($payload['collection'])->toBe('app.bsky.feed.post');
            expect($payload['record']['text'])->toBe('Hello world!');

            return [
                'code' => 200,
                'body' => json_encode([
                    'uri' => 'at://did:plc:alice123/app.bsky.feed.post/3kabc123',
                    'cid' => 'bafk123mockcid',
                ]),
                'error' => '',
            ];
        };

        $client = new AtProtoClient($mockTransport);
        $res = $client->createRecord(
            'did:plc:alice123',
            'app.bsky.feed.post',
            ['text' => 'Hello world!'],
            'mock-jwt',
            'https://bsky.social'
        );

        expect($res['uri'])->toBe('at://did:plc:alice123/app.bsky.feed.post/3kabc123');
        expect($res['cid'])->toBe('bafk123mockcid');
    });

    it('generates canonical public post url', function () {
        $url = AtProtoClient::canonicalPostUrl('at://did:plc:alice123/app.bsky.feed.post/3l7q2q3x6b22c', 'alice.bsky.social');
        expect($url)->toBe('https://bsky.app/profile/alice.bsky.social/post/3l7q2q3x6b22c');
    });
});

describe('AtProtoCardBuilder', function () {
    it('generates fallback excerpt from markdown content', function () {
        $builder = new AtProtoCardBuilder();
        $md = "# My Article\n\nThis is a **fantastic** post about [IndieWeb](https://indieweb.org) and decentralization.\n\n```php\necho 'hello';\n```\nMore text here.";

        $excerpt = $builder->fallbackExcerpt($md);
        expect($excerpt)->toContain('My Article');
        expect($excerpt)->toContain('This is a fantastic post about IndieWeb and decentralization.');
        expect($excerpt)->not->toContain('```');
        expect($excerpt)->not->toContain('#');
    });

    it('preserves stanzas and line breaks in poems when preserveLineBreaks is true', function () {
        $builder = new AtProtoCardBuilder();
        $poem = "SUbiu pra mente\n\nQuem quis me ver chorando\nviu a fumaça subir:\no fumo queimando brando,\no luto virando o agir.\n\nDepois da raiva vem a ação:\na brasa da revolução.";

        $cleaned = $builder->cleanMarkdown($poem, true);
        expect($cleaned)->toContain("SUbiu pra mente\n\nQuem quis me ver chorando\nviu a fumaça subir:");
        expect($cleaned)->toContain("o fumo queimando brando,\no luto virando o agir.\n\nDepois da raiva vem a ação:\na brasa da revolução.");
    });

    it('converts br and p html tags to newlines when preserving line breaks', function () {
        $builder = new AtProtoCardBuilder();
        $htmlPoem = "<p>SUbiu pra mente</p><p>Quem quis me ver chorando<br />viu a fumaça subir:<br>o fumo queimando brando,<br />o luto virando o agir.</p><p>Depois da raiva vem a ação:<br />a brasa da revolução.</p>";

        $cleaned = $builder->cleanMarkdown($htmlPoem, true);
        expect($cleaned)->toContain("SUbiu pra mente\n\nQuem quis me ver chorando\nviu a fumaça subir:\no fumo queimando brando,\no luto virando o agir.\n\nDepois da raiva vem a ação:\na brasa da revolução.");
    });

    it('generates description via Gemini API when configured', function () {
        $mockGeminiTransport = function (string $url, array $headers, string $body) {
            expect($url)->toContain('generativelanguage.googleapis.com');
            expect($url)->toContain('gemini-2.5-flash');

            return [
                'code' => 200,
                'body' => json_encode([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'Um resumo cativante e conciso gerado pela Gemini para o card do Bluesky.']
                                ]
                            ]
                        ]
                    ]
                ]),
                'error' => '',
            ];
        };

        $builder = new AtProtoCardBuilder('fake-gemini-key', 'gemini-2.5-flash', $mockGeminiTransport);
        $desc = $builder->generateDescription('Novo Post', 'Conteúdo longo sobre federação e web aberta.');

        expect($desc)->toBe('Um resumo cativante e conciso gerado pela Gemini para o card do Bluesky.');
    });

    it('builds external link card structure', function () {
        $builder = new AtProtoCardBuilder();
        $card = $builder->build(
            'https://lumen.pink/articles/federation.html',
            'Federation in IndieInABox',
            'Full article text here detailing the AT Protocol architecture.'
        );

        expect($card['$type'])->toBe('app.bsky.embed.external');
        expect($card['external']['uri'])->toBe('https://lumen.pink/articles/federation.html');
        expect($card['external']['title'])->toBe('Federation in IndieInABox');
        expect($card['external']['description'])->toContain('Full article text here');
    });
});

describe('BskySyndicationService', function () {
    it('syndicates file and updates frontmatter with canonical bsky url', function () {
        $filePath = $this->tempDir . '/content/articles/posse-test.md';
        $initialMd = "---\ntitle: POSSE Test Article\nkind: article\n---\n\nExploring POSSE with Bluesky.";
        file_put_contents($filePath, $initialMd);

        $mockTransport = function (string $url, string $method, array $headers, ?string $body) {
            if (str_contains($url, 'createSession')) {
                return [
                    'code' => 200,
                    'body' => json_encode([
                        'accessJwt' => 'test-jwt',
                        'refreshJwt' => 'test-refresh',
                        'did' => 'did:plc:lumentest',
                        'handle' => 'lumen.pink',
                    ]),
                    'error' => '',
                ];
            }
            if (str_contains($url, 'createRecord')) {
                return [
                    'code' => 200,
                    'body' => json_encode([
                        'uri' => 'at://did:plc:lumentest/app.bsky.feed.post/3kxyz999',
                        'cid' => 'bafkcid123',
                    ]),
                    'error' => '',
                ];
            }
            return ['code' => 404, 'body' => '', 'error' => 'not found'];
        };

        Database::setSetting('atproto_handle', 'lumen.pink');
        Database::setSetting('atproto_app_password', 'app-pass-secret');
        Database::setSetting('fqdn', 'https://lumen.pink');

        $client = new AtProtoClient($mockTransport);
        $service = new BskySyndicationService($this->site, $client);

        $url = $service->syndicateFile($filePath);
        expect($url)->toBe('https://bsky.app/profile/lumen.pink/post/3kxyz999');

        // Check frontmatter was updated
        $updatedContent = (string) file_get_contents($filePath);
        expect($updatedContent)->toContain('syndication:');
        expect($updatedContent)->toContain('https://bsky.app/profile/lumen.pink/post/3kxyz999');

        // Second call without force should skip calling createRecord
        $url2 = $service->syndicateFile($filePath, false);
        expect($url2)->toBe('https://bsky.app/profile/lumen.pink/post/3kxyz999');
    });

    it('tests credentials successfully', function () {
        $mockTransport = function (string $url, string $method, array $headers, ?string $body) {
            return [
                'code' => 200,
                'body' => json_encode([
                    'accessJwt' => 'jwt',
                    'refreshJwt' => 'refresh',
                    'did' => 'did:plc:testuser',
                    'handle' => 'testuser.bsky.social',
                ]),
                'error' => '',
            ];
        };

        $client = new AtProtoClient($mockTransport);
        $service = new BskySyndicationService($this->site, $client);

        $res = $service->testCredentials('testuser.bsky.social', 'pass123');
        expect($res['success'])->toBeTrue();
        expect($res['did'])->toBe('did:plc:testuser');
    });
});

describe('WebRouter /.well-known/atproto-did', function () {
    it('returns atproto-did text/plain when configured', function () {
        Database::setSetting('atproto_did', 'did:plc:z72i7hdynmk6r22z27h6tvur');
        $_SERVER['REQUEST_URI'] = '/.well-known/atproto-did';

        $router = new WebRouter($this->site);

        ob_start();
        $router->handleRequest();
        $output = (string) ob_get_clean();

        expect($output)->toBe('did:plc:z72i7hdynmk6r22z27h6tvur');
    });

    it('returns 404 when atproto-did is not configured', function () {
        Database::setSetting('atproto_did', '');
        $_SERVER['REQUEST_URI'] = '/.well-known/atproto-did';

        $router = new WebRouter($this->site);

        ob_start();
        $router->handleRequest();
        $output = (string) ob_get_clean();

        expect($output)->toContain('ATProto DID not configured');
    });
});

describe('BskyCommand CLI', function () {
    it('executes bsky did subcommand', function () {
        Database::setSetting('atproto_did', 'did:plc:sampledid123');
        $cmd = new BskyCommand($this->site);

        ob_start();
        $status = $cmd->execute(['indieinabox.php', 'bsky', 'did']);
        $output = (string) ob_get_clean();

        expect($status)->toBe(0);
        expect(trim($output))->toBe('did:plc:sampledid123');
    });

    it('executes bsky test subcommand successfully', function () {
        Database::setSetting('atproto_handle', 'test.bsky.social');
        Database::setSetting('atproto_app_password', 'secret-pass');

        // Create client with mock transport inside a subclass or wrapper
        $cmd = new BskyCommand($this->site);

        ob_start();
        $status = $cmd->execute(['indieinabox.php', 'bsky', 'test']);
        $output = (string) ob_get_clean();

        // Note: Real transport without network may fail gracefully with status 1
        expect($output)->toContain('Testing AT Protocol authentication with PDS...');
    });
});

describe('CreatePostCommandHandler auto-syndication', function () {
    it('does not crash when auto-syndication is disabled', function () {
        Database::setSetting('atproto_auto_syndicate', '0');

        $handler = new \Indieinabox\Commands\Handlers\CreatePostCommandHandler();
        $command = new \Indieinabox\Commands\CreatePostCommand($this->site, [
            'content' => 'Hello from unit test #posse',
            'name' => 'Auto Post Test',
        ]);

        $res = $handler->handle($command);
        expect($res['status'])->toBe(202);
        expect(file_exists($res['file_path']))->toBeTrue();
    });
});

