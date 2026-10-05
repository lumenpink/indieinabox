<?php

declare(strict_types=1);

namespace Indieinabox\Demo;

use Indieinabox\Core\Database;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\SiteBuilder;
use Indieinabox\Support\FileUtils;

/**
 * Class DemoService
 *
 * Manages the lifecycle of interactive public sandbox demo environments:
 * initial seeding of rich sample data, golden state snapshotting, and periodic rollback.
 */
class DemoService
{
    private Site $site;

    public function __construct(?Site $site = null)
    {
        $this->site = $site ?? new Site();
    }

    /**
     * Seeds the demo environment with initial settings and sample content,
     * taking a golden snapshot into data/.demo_seed/.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function seed(array $options = []): array
    {
        $this->ensureConnected();

        $fqdn = rtrim((string) ($options['fqdn'] ?? 'https://demo.indieinabox.2lp.in'), '/');
        $sitename = (string) ($options['sitename'] ?? 'IndieInABox Public Sandbox');
        $author = (string) ($options['author'] ?? 'Demo Admin');
        $password = (string) ($options['password'] ?? 'demo123');

        $this->site->metadata->fqdn = $fqdn;
        $this->site->metadata->sitename = $sitename;
        $this->site->metadata->author = $author;

        // Persist settings
        Database::setSetting('sitename', $sitename);
        Database::setSetting('author', $author);
        Database::setSetting('fqdn', $fqdn);
        Database::setSetting('indieauth_password', password_hash($password, PASSWORD_BCRYPT));
        Database::setSetting('demo_mode', '1');
        Database::setSetting('demo_reset_interval', '3600');
        Database::setSetting('demo_last_reset', (string) time());

        // Create sample content
        $contentPath = $this->site->paths->getContentPath();
        $this->generateSampleContent($contentPath);

        // Build static site
        try {
            $builder = new SiteBuilder($this->site);
            $builder->build();
        } catch (\Throwable) {
        }

        // Take golden snapshot
        $dataDir = Database::$dataDir ?: (rtrim($this->site->paths->baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'data');
        $seedDir = $dataDir . DIRECTORY_SEPARATOR . '.demo_seed';
        if (!is_dir($seedDir)) {
            @mkdir($seedDir, 0755, true);
        }

        // Checkpoint WAL and take golden snapshot
        $sqlitePath = $dataDir . DIRECTORY_SEPARATOR . 'indieinabox.sqlite';
        $goldenSqlite = $seedDir . DIRECTORY_SEPARATOR . 'demo.sqlite';
        if (Database::isConnected()) {
            try {
                Database::getDb()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            } catch (\Throwable) {
            }
        }
        if (file_exists($goldenSqlite)) {
            @unlink($goldenSqlite);
        }
        $snapshotTaken = false;
        if (Database::isConnected()) {
            try {
                Database::getDb()->exec('VACUUM INTO ' . Database::getDb()->quote($goldenSqlite));
                $snapshotTaken = true;
            } catch (\Throwable) {
            }
        }
        if (!$snapshotTaken && file_exists($sqlitePath)) {
            @copy($sqlitePath, $goldenSqlite);
        }

        // Copy content directory
        $seedContentDir = $seedDir . DIRECTORY_SEPARATOR . 'content';
        FileUtils::recursiveRmdir($seedContentDir);
        FileUtils::copyDirectory($contentPath, $seedContentDir);

        return [
            'success' => true,
            'seeded_at' => time(),
            'fqdn' => $fqdn,
            'sitename' => $sitename,
        ];
    }

    /**
     * Rolls back the demo sandbox to the golden seed snapshot.
     *
     * @return array<string, mixed>
     */
    public function reset(): array
    {
        $dataDir = Database::$dataDir ?: (rtrim($this->site->paths->baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'data');
        $seedDir = $dataDir . DIRECTORY_SEPARATOR . '.demo_seed';
        $goldenSqlite = $seedDir . DIRECTORY_SEPARATOR . 'demo.sqlite';
        $goldenContent = $seedDir . DIRECTORY_SEPARATOR . 'content';

        if (!file_exists($goldenSqlite)) {
            return [
                'success' => false,
                'error' => 'No golden seed database found in .demo_seed',
            ];
        }

        // 1. Disconnect DB and flush container
        Database::disconnect();
        if (class_exists(\Indieinabox\Core\Container::class)) {
            \Indieinabox\Core\Container::getInstance()->flush();
        }
        gc_collect_cycles();

        // 2. Restore SQLite database
        $sqlitePath = $dataDir . DIRECTORY_SEPARATOR . 'indieinabox.sqlite';
        if (file_exists($sqlitePath . '-wal')) {
            @unlink($sqlitePath . '-wal');
        }
        if (file_exists($sqlitePath . '-shm')) {
            @unlink($sqlitePath . '-shm');
        }
        if (file_exists($sqlitePath)) {
            @unlink($sqlitePath);
        }
        @copy($goldenSqlite, $sqlitePath);

        // Reconnect and stamp last reset
        Database::connect($sqlitePath);
        Database::setSetting('demo_last_reset', (string) time());

        // 3. Restore content directory
        $contentPath = $this->site->paths->getContentPath();
        FileUtils::recursiveRmdir($contentPath);
        if (is_dir($goldenContent)) {
            FileUtils::copyDirectory($goldenContent, $contentPath);
        }

        // 4. Rebuild static site
        try {
            $builder = new SiteBuilder($this->site);
            $builder->build();
        } catch (\Throwable) {
        }

        return [
            'success' => true,
            'reset_at' => time(),
        ];
    }

    /**
     * Retrieves status metadata for the demo sandbox.
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        $this->ensureConnected();

        $demoMode = ((string) Database::getSetting('demo_mode') === '1') || (getenv('DEMO_MODE') === 'true');
        $lastReset = (int) (Database::getSetting('demo_last_reset') ?? 0);
        $interval = (int) (Database::getSetting('demo_reset_interval') ?? 3600);
        $nextReset = $lastReset > 0 ? $lastReset + $interval : 0;

        return [
            'demo_mode' => $demoMode,
            'last_reset' => $lastReset,
            'last_reset_iso' => $lastReset > 0 ? date(DATE_ATOM, $lastReset) : null,
            'interval_seconds' => $interval,
            'next_reset' => $nextReset,
            'next_reset_iso' => $nextReset > 0 ? date(DATE_ATOM, $nextReset) : null,
        ];
    }

    /**
     * Checks if it is time to perform an automated reset.
     */
    public function shouldReset(): bool
    {
        $status = $this->getStatus();
        if (!$status['demo_mode']) {
            return false;
        }

        if ($status['last_reset'] === 0) {
            return true;
        }

        return time() >= $status['next_reset'];
    }

    /**
     * Ensures database is initialized and connected before executing demo operations.
     */
    private function ensureConnected(): void
    {
        $baseDir = $this->site->paths->baseDir;
        $configFile = $baseDir . '/.config.php';
        if (!file_exists($configFile)) {
            $configContent = "<?php return ['data_dir' => __DIR__ . '/data', 'db_path' => __DIR__ . '/data/indieinabox.sqlite'];\n";
            @file_put_contents($configFile, $configContent);
        }

        $dataDir = Database::$dataDir ?: (rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'data');
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0755, true);
        }

        $sqlitePath = $dataDir . DIRECTORY_SEPARATOR . 'indieinabox.sqlite';
        if (!Database::isConnected()) {
            Database::$dataDir = $dataDir;
            try {
                Database::connect($sqlitePath);
            } catch (\Throwable) {
            }
        }

        // Initialize schema if settings table does not exist
        if (Database::isConnected()) {
            try {
                $db = Database::getDb();
                $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'")->fetchColumn();
                if (!$check) {
                    Database::initializeSchema($db);
                }
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Populates curated sample markdown files in content/.
     */
    private function generateSampleContent(string $contentPath): void
    {
        $articlesDir = $contentPath . DIRECTORY_SEPARATOR . 'articles';
        $notesDir = $contentPath . DIRECTORY_SEPARATOR . 'notes';
        $gardenDir = $contentPath . DIRECTORY_SEPARATOR . 'garden';

        @mkdir($articlesDir, 0755, true);
        @mkdir($notesDir, 0755, true);
        @mkdir($gardenDir, 0755, true);

        // 1. Sample Article
        $articleContent = <<<MARKDOWN
---
title: Welcome to the IndieInABox Public Sandbox!
date: 2026-10-04 12:00:00
tags: [indieweb, demo, welcome]
kind: article
---
Welcome to the interactive demonstration instance of **IndieInABox**!

Here you can explore:
- **Web Admin Panel** at `/admin/config` (log in with password `demo123`).
- **Micropub Posting Client** at `/micropub/client`.
- **Microsub Feed Reader** at `/microsub/reader`.
- **Nostr & ActivityPub Federation** settings.

> ℹ️ **Notice**: This public sandbox automatically rolls back and cleans itself every **60 minutes**. Feel free to experiment, publish new articles, change themes, and configure settings!
MARKDOWN;
        @file_put_contents($articlesDir . DIRECTORY_SEPARATOR . 'welcome.md', $articleContent);

        // 2. Sample Note
        $noteContent = <<<MARKDOWN
---
date: 2026-10-04 13:00:00
tags: [posse, demo]
kind: note
---
Testing POSSE syndication across the decentralized web! #indieweb #fediverse #nostr
MARKDOWN;
        @file_put_contents($notesDir . DIRECTORY_SEPARATOR . 'posse-note.md', $noteContent);

        // 3. Sample Garden sprout
        $gardenContent = <<<MARKDOWN
---
title: Digital Gardens and Permaculture in Cyberspace
date: 2026-10-04 14:00:00
flowerbed: [indieweb]
maturity: seedling
kind: garden
---
Digital gardening focuses on nurturing ideas slowly over time rather than chronological stream publishing.
MARKDOWN;
        @file_put_contents($gardenDir . DIRECTORY_SEPARATOR . 'digital-gardens.md', $gardenContent);
    }
}
