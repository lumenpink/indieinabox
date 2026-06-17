<?php

declare(strict_types=1);

use Indieinabox\Site;
use Indieinabox\WebRouter;
use Indieinabox\Yaml;

$authFuncTempDir = __DIR__ . '/tmp_auth_functional';

beforeEach(function () use ($authFuncTempDir) {
    if (!is_dir($authFuncTempDir)) {
        mkdir($authFuncTempDir, 0777, true);
    }
    $_GET = [];
    $_POST = [];
    $_SERVER = [];
});

afterEach(function () use ($authFuncTempDir) {
    if (is_dir($authFuncTempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($authFuncTempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
            @$todo($fileinfo->getPathname());
        }
        @rmdir($authFuncTempDir);
    }
});

it('prioritizes hidden .config.yml configuration file', function () use ($authFuncTempDir) {
    // Write both config.yml and .config.yml
    $yaml = new Yaml();
    
    $configDefault = ['title' => 'Default Title', 'fqdn' => 'https://default.com/'];
    $configHidden = ['title' => 'Hidden Title', 'fqdn' => 'https://hidden.com/', 'indieauth_password' => 'secret123'];
    
    file_put_contents($authFuncTempDir . '/config.yml', $yaml->dump($configDefault));
    file_put_contents($authFuncTempDir . '/.config.yml', $yaml->dump($configHidden));
    
    // Simulate build.php config resolution
    $base = $authFuncTempDir;
    $configFile = $base . DIRECTORY_SEPARATOR . "config.yml";
    if (file_exists($base . DIRECTORY_SEPARATOR . ".config.yml")) {
        $configFile = $base . DIRECTORY_SEPARATOR . ".config.yml";
    }
    $config = $yaml->loadFile($configFile);
    
    $site = new Site();
    $site->metadata->title = $config['title'] ?? 'Default Title';
    $site->metadata->fqdn = $config['fqdn'] ?? 'https://default.com/';
    $site->metadata->indieauthPassword = (string)($config['indieauth_password'] ?? '');

    expect($site->metadata->title)->toBe('Hidden Title')
        ->and($site->metadata->fqdn)->toBe('https://hidden.com/')
        ->and($site->metadata->indieauthPassword)->toBe('secret123');
});

it('renders authorization form and processes login with plain text password', function () use ($authFuncTempDir) {
    $site = new Site();
    $site->paths->baseDir = $authFuncTempDir;
    $site->metadata->fqdn = 'https://mysite.com';
    $site->metadata->indieauthPassword = 'mypassword';

    // 1. GET Request
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/auth';
    $_GET = [
        'client_id' => 'https://app.com/',
        'redirect_uri' => 'https://app.com/redirect',
        'state' => 'xyz123',
        'scope' => 'create update',
        'code_challenge' => 'E9Melhoa2OwvFrGMTJguCH5KGS2y',
        'code_challenge_method' => 'plain'
    ];

    $router = new WebRouter($site);
    
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    expect($output)->toContain('IndieAuth Request')
        ->and($output)->toContain('https://app.com/')
        ->and($output)->toContain('create update');

    // 2. POST Login Request (Correct password)
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/auth';
    $_POST = [
        'client_id' => 'https://app.com/',
        'redirect_uri' => 'https://app.com/redirect',
        'state' => 'xyz123',
        'scope' => 'create update',
        'code_challenge' => 'E9Melhoa2OwvFrGMTJguCH5KGS2y',
        'code_challenge_method' => 'plain',
        'password' => 'mypassword'
    ];

    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    // Verify redirect headers are generated in PHPUnit environment (can inspect output/headers)
    // A file should be written under data/indieauth/codes
    $codesDir = $authFuncTempDir . '/data/indieauth/codes';
    expect(is_dir($codesDir))->toBeTrue();
    
    $files = glob($codesDir . '/*.json');
    expect($files)->toHaveCount(1);
    
    $codeData = json_decode(file_get_contents($files[0]), true);
    expect($codeData['client_id'])->toBe('https://app.com/')
        ->and($codeData['scope'])->toBe('create update')
        ->and($codeData['code_challenge'])->toBe('E9Melhoa2OwvFrGMTJguCH5KGS2y')
        ->and($codeData['code_challenge_method'])->toBe('plain');
});

it('processes login with bcrypt-hashed password', function () use ($authFuncTempDir) {
    $site = new Site();
    $site->paths->baseDir = $authFuncTempDir;
    $site->metadata->fqdn = 'https://mysite.com';
    $site->metadata->indieauthPassword = password_hash('mysecurepwd', PASSWORD_BCRYPT);

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/auth';
    $_POST = [
        'client_id' => 'https://app.com/',
        'redirect_uri' => 'https://app.com/redirect',
        'state' => 'xyz123',
        'scope' => 'create update',
        'password' => 'mysecurepwd'
    ];

    $router = new WebRouter($site);
    ob_start();
    $router->handleRequest();
    ob_get_clean();

    $codesDir = $authFuncTempDir . '/data/indieauth/codes';
    $files = glob($codesDir . '/*.json');
    expect($files)->toHaveCount(1);
});

it('verifies authorization code and exchanges it for access token with PKCE S256 validation', function () use ($authFuncTempDir) {
    $site = new Site();
    $site->paths->baseDir = $authFuncTempDir;
    $site->metadata->fqdn = 'https://mysite.com';
    
    // Prepare stored auth code manually
    $code = 'authcode_123';
    $codeChallenge = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash('sha256', 'my_code_verifier_123_abc', true)));
    
    $codeData = [
        'code' => $code,
        'client_id' => 'https://app.com/',
        'redirect_uri' => 'https://app.com/redirect',
        'state' => 'xyz123',
        'scope' => 'create update',
        'code_challenge' => $codeChallenge,
        'code_challenge_method' => 'S256',
        'expires_at' => time() + 600,
        'me' => 'https://mysite.com/'
    ];
    
    $codesDir = $authFuncTempDir . '/data/indieauth/codes';
    mkdir($codesDir, 0777, true);
    file_put_contents($codesDir . '/' . md5($code) . '.json', json_encode($codeData));

    // 1. Client app calls auth endpoint to verify the code (POST with 'code' parameter)
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/auth';
    $_POST = [
        'code' => $code,
        'client_id' => 'https://app.com/',
        'redirect_uri' => 'https://app.com/redirect',
        'code_verifier' => 'my_code_verifier_123_abc'
    ];

    $router = new WebRouter($site);
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    $json = json_decode($output, true);
    expect($json)->toBeArray()
        ->and($json['me'])->toBe('https://mysite.com/')
        ->and($json['scope'])->toBe('create update');

    // Code must be deleted after use
    expect(file_exists($codesDir . '/' . md5($code) . '.json'))->toBeFalse();

    // 2. Token exchange flow
    // Re-write code file
    file_put_contents($codesDir . '/' . md5($code) . '.json', json_encode($codeData));

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/token';
    $_POST = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'client_id' => 'https://app.com/',
        'redirect_uri' => 'https://app.com/redirect',
        'code_verifier' => 'my_code_verifier_123_abc'
    ];

    ob_start();
    $router->handleRequest();
    $outputToken = ob_get_clean();

    $tokenJson = json_decode($outputToken, true);
    expect($tokenJson)->toBeArray()
        ->and($tokenJson['access_token'])->toBeString()
        ->and($tokenJson['me'])->toBe('https://mysite.com/')
        ->and($tokenJson['scope'])->toBe('create update');

    $accessToken = $tokenJson['access_token'];

    // 3. Verify issued access token via token endpoint GET with Authorization header
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/token';
    $_POST = [];
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $accessToken;

    ob_start();
    $router->handleRequest();
    $outputVerify = ob_get_clean();

    $verifyJson = json_decode($outputVerify, true);
    expect($verifyJson)->toBeArray()
        ->and($verifyJson['me'])->toBe('https://mysite.com/')
        ->and($verifyJson['client_id'])->toBe('https://app.com/')
        ->and($verifyJson['scope'])->toBe('create update');
});
