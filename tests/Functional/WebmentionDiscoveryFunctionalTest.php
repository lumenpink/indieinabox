<?php

declare(strict_types=1);

namespace Tests\Functional;

use PHPUnit\Framework\TestCase;
use Indieinabox\BackgroundWorker;
use Indieinabox\Site;
use Indieinabox\Database;

class WebmentionDiscoveryFunctionalTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = __DIR__ . '/test_worker_discovery.sqlite';
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
        
        Database::connect($this->dbPath);
    }

    protected function tearDown(): void
    {
        Database::disconnect();
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    public function testProcessWebmentionDiscoveryUpdatesQueue(): void
    {
        $db = Database::getDb();
        
        // Insert a dummy domain that needs checking
        $stmt = $db->prepare("INSERT INTO webmention_discovery_cache (domain, supports_webmention, last_checked) VALUES ('localhost', 0, 0)");
        $stmt->execute();

        // Create a minimal Site mock (if needed by BackgroundWorker)
        $site = new Site();

        $worker = new BackgroundWorker($site);
        
        // Capture output to prevent clutter
        ob_start();
        $worker->processWebmentionDiscovery();
        $output = ob_get_clean();

        $this->assertStringContainsString('Checking https://localhost/', $output);

        // Verify the database was updated
        $stmt = $db->prepare("SELECT last_checked, supports_webmention FROM webmention_discovery_cache WHERE domain = 'localhost'");
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertGreaterThan(0, $row['last_checked']);
        // Since localhost doesn't actually have webmention headers here, it should be 0, 
        // but the fact that last_checked updated proves the worker ran.
        $this->assertEquals(0, $row['supports_webmention']);
    }
}
