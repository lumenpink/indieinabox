<?php

declare(strict_types=1);

use Indieinabox\Database;
use Indieinabox\IndieAuth\TokenManager;
use Indieinabox\IndieAuth\PkceValidator;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_token_mgr_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;
    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);

    $db = Database::getDb();
    $db->exec('CREATE TABLE IF NOT EXISTS indieauth_codes (
        code_hash TEXT PRIMARY KEY,
        client_id TEXT NOT NULL,
        redirect_uri TEXT NOT NULL,
        me TEXT NOT NULL,
        scope TEXT,
        state TEXT,
        code_challenge TEXT,
        code_challenge_method TEXT,
        expires_at INTEGER NOT NULL
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS indieauth_tokens (
        token_hash TEXT PRIMARY KEY,
        client_id TEXT NOT NULL,
        scope TEXT,
        me TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )');

    $this->tokenManager = new TokenManager($db);
});

afterEach(function () {
    /** @var \Tests\TestCase $this */
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('TokenManager creates authorization code and verifies it', function () {
    /** @var \Tests\TestCase $this */
    $code = $this->tokenManager->createAuthorizationCode(
        'https://app.example.com',
        'https://app.example.com/redirect',
        'https://author.example.com/',
        'create update'
    );

    expect($code)->toHaveLength(32);

    $result = $this->tokenManager->verifyAuthorizationCode(
        $code,
        'https://app.example.com',
        'https://app.example.com/redirect'
    );

    expect($result)->toHaveKey('me', 'https://author.example.com/')
        ->toHaveKey('scope', 'create update');

    // Code is one-time use; second verification must fail
    $retry = $this->tokenManager->verifyAuthorizationCode(
        $code,
        'https://app.example.com',
        'https://app.example.com/redirect'
    );
    expect($retry)->toHaveKey('error');
});

test('TokenManager verifies PKCE challenge on code verification', function () {
    /** @var \Tests\TestCase $this */
    $verifier = 'secret-pkce-verifier-string-12345';
    $challenge = PkceValidator::calculateChallenge($verifier, 'S256');

    $code = $this->tokenManager->createAuthorizationCode(
        'https://app.example.com',
        'https://app.example.com/redirect',
        'https://author.example.com/',
        'create',
        $challenge,
        'S256'
    );

    // Fail with wrong verifier
    $fail = $this->tokenManager->verifyAuthorizationCode(
        $code,
        'https://app.example.com',
        'https://app.example.com/redirect',
        'wrong-verifier'
    );
    expect($fail)->toHaveKey('error');

    // Create a fresh code and succeed with correct verifier
    $code2 = $this->tokenManager->createAuthorizationCode(
        'https://app.example.com',
        'https://app.example.com/redirect',
        'https://author.example.com/',
        'create',
        $challenge,
        'S256'
    );

    $success = $this->tokenManager->verifyAuthorizationCode(
        $code2,
        'https://app.example.com',
        'https://app.example.com/redirect',
        $verifier
    );
    expect($success)->toHaveKey('me', 'https://author.example.com/');
});

test('TokenManager exchanges authorization code for bearer access token', function () {
    /** @var \Tests\TestCase $this */
    $code = $this->tokenManager->createAuthorizationCode(
        'https://app.example.com',
        'https://app.example.com/redirect',
        'https://author.example.com/',
        'create media'
    );

    $tokenData = $this->tokenManager->exchangeCodeForToken(
        $code,
        'https://app.example.com',
        'https://app.example.com/redirect'
    );

    expect($tokenData)->toHaveKey('access_token')
        ->toHaveKey('token_type', 'Bearer')
        ->toHaveKey('me', 'https://author.example.com/')
        ->toHaveKey('scope', 'create media');

    $accessToken = $tokenData['access_token'];

    // Validate the bearer token via Authorization header
    $authResult = $this->tokenManager->validateBearerToken($out, "Bearer $accessToken");
    expect($authResult)->not->toBeNull();
    expect($authResult['me'])->toBe('https://author.example.com/');
    expect($authResult['client_id'])->toBe('https://app.example.com');
    expect($authResult['scope'])->toBe('create media');
    expect($out)->toBe($accessToken);

    // Validate with invalid token
    $invalid = $this->tokenManager->validateBearerToken($out, "Bearer invalid-token");
    expect($invalid)->toBeNull();
});
