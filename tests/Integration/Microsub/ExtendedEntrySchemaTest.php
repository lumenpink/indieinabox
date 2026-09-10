<?php

declare(strict_types=1);

namespace Tests\Integration\Microsub;

use PHPUnit\Framework\TestCase;
use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;

/**
 * Integration tests that validate the canonical JSON example files
 * against the formal ExtendedEntry JSON Schema.
 * 
 * This ensures that any change to the schema or the examples
 * keeps both in sync and valid.
 */
class ExtendedEntrySchemaTest extends TestCase
{
    private Validator $validator;
    private string $schemaPath;
    private string $examplesDir;

    protected function setUp(): void
    {
        $this->validator = new Validator();
        $this->schemaPath = realpath(__DIR__ . '/../../../docs/api/extended_entry_schema.json');
        $this->examplesDir = realpath(__DIR__ . '/../../../docs/api/examples');
    }

    private function validate(string $jsonFile): void
    {
        $schema = json_decode(file_get_contents($this->schemaPath));
        $data = json_decode(file_get_contents($jsonFile));

        $result = $this->validator->validate($data, $schema);

        if (!$result->isValid()) {
            $formatter = new ErrorFormatter();
            $errors = $formatter->format($result->error());
            $this->fail("Schema validation failed for {$jsonFile}:\n" . json_encode($errors, JSON_PRETTY_PRINT));
        }

        $this->assertTrue(true); // explicit pass
    }

    public function testActivityPubExampleIsValid(): void
    {
        $this->validate($this->examplesDir . '/entry_activitypub.json');
    }

    public function testTwtxtExampleIsValid(): void
    {
        $this->validate($this->examplesDir . '/entry_twtxt.json');
    }

    public function testRssWebmentionExampleIsValid(): void
    {
        $this->validate($this->examplesDir . '/entry_rss_webmention.json');
    }

    public function testRssLocalOnlyExampleIsValid(): void
    {
        $this->validate($this->examplesDir . '/entry_rss_local_only.json');
    }

    public function testInvalidCapabilityIsRejected(): void
    {
        $schema = json_decode(file_get_contents($this->schemaPath));
        $data = json_decode(json_encode([
            'type' => 'entry',
            'uid' => 'https://example.com/1',
            'url' => 'https://example.com/1',
            'published' => '2026-09-09T00:00:00Z',
            'content' => ['html' => '<p>hi</p>', 'text' => 'hi'],
            '_indieinabox' => [
                'network' => 'activitypub',
                'origin_server' => 'example.com',
                'capabilities' => ['INVALID_CAPABILITY']
            ]
        ]));

        $result = $this->validator->validate($data, $schema);
        $this->assertFalse($result->isValid(), 'Schema should reject unknown capabilities');
    }

    public function testInvalidNetworkIsRejected(): void
    {
        $schema = json_decode(file_get_contents($this->schemaPath));
        $data = json_decode(json_encode([
            'type' => 'entry',
            'uid' => 'https://example.com/1',
            'url' => 'https://example.com/1',
            'published' => '2026-09-09T00:00:00Z',
            'content' => ['html' => '<p>hi</p>', 'text' => 'hi'],
            '_indieinabox' => [
                'network' => 'bluesky', // not in enum
                'origin_server' => 'bsky.social',
                'capabilities' => ['reply']
            ]
        ]));

        $result = $this->validator->validate($data, $schema);
        $this->assertFalse($result->isValid(), 'Schema should reject unknown network values');
    }
}
