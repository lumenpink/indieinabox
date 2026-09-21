<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Http\Controllers\AdminController;
use Indieinabox\Repositories\SqliteSettingsRepository;
use Indieinabox\Services\ExportService;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use Indieinabox\Views\Admin\ConfigView;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_admin_export_test_' . bin2hex(random_bytes(6));
    @mkdir($this->tempDir . '/data', 0755, true);
    @mkdir($this->tempDir . '/content/notes', 0755, true);
    @mkdir($this->tempDir . '/public_media', 0755, true);

    file_put_contents($this->tempDir . '/content/notes/admin_note.md', '# Admin Note Content');
    file_put_contents($this->tempDir . '/public_media/photo.jpg', 'binaryphoto');

    Database::$dataDir = $this->tempDir . '/data';
    $dbPath = $this->tempDir . '/data/database.sqlite';
    Database::connect($dbPath);
    $sql = (string) file_get_contents(dirname(__DIR__, 3) . '/database.sql');
    Database::getDb()->exec($sql);
    Database::getDb()->exec('CREATE TABLE IF NOT EXISTS admin_items (id INTEGER PRIMARY KEY, name TEXT)');
    Database::getDb()->exec("INSERT INTO admin_items (name) VALUES ('Initial Item')");

    $this->paths = new Paths($this->tempDir);
    $this->site = new Site(null, $this->paths);
    $this->site->metadata->fqdn = 'https://admin.example';
    $this->site->metadata->indieauthPassword = 'test-hash';

    $repo = new SqliteSettingsRepository();
    $repo->set('indieauth_password', 'test-hash');

    $_GET = [];
    $_POST = [];
    $_SERVER = [];
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION = ['admin_authenticated' => true];
    $_FILES = [];
    http_response_code(200);

    Container::getInstance()->flush();
    Container::getInstance()->instance(Site::class, $this->site);
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    Container::getInstance()->flush();
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }
});

test('ConfigView renders Portable Data Export and Import fieldsets in backups tab', function () {
    $config = [
        'backup_dir' => '../backup',
        'backup_limit' => 5,
        'backup_cron_enabled' => false,
    ];

    $html = ConfigView::renderConfig($this->site, $config);

    expect($html)->toContain('Portable Data Export')
                 ->toContain('Export &amp; Download Bundle (.tar.gz)')
                 ->toContain('Portable Data Import &amp; Restore')
                 ->toContain('Upload &amp; Restore Bundle');
});

test('AdminController handles import_upload action and restores site state', function () {
    // 1. Generate export bundle
    $exportService = new ExportService($this->site);
    $bundleFile = $this->tempDir . '/test_backup.tar.gz';
    $exportService->export($bundleFile);

    // 2. Modify state
    Database::getDb()->exec("UPDATE admin_items SET name = 'Modified Item' WHERE id = 1");
    file_put_contents($this->tempDir . '/content/notes/admin_note.md', '# Altered Content');

    // 3. Prepare POST request for AdminController
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['action'] = 'import_upload';
    $_FILES['import_file'] = [
        'name' => 'test_backup.tar.gz',
        'type' => 'application/gzip',
        'tmp_name' => $bundleFile,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($bundleFile),
    ];

    $controller = new AdminController($this->site);

    ob_start();
    $controller->config();
    ob_get_clean();

    // 4. Verify DB was restored
    $stmt = Database::getDb()->query('SELECT name FROM admin_items WHERE id = 1');
    expect($stmt->fetchColumn())->toBe('Initial Item');

    // 5. Verify file was restored
    expect(file_get_contents($this->tempDir . '/content/notes/admin_note.md'))->toBe('# Admin Note Content');
});

test('AdminController redirects with import_error when corrupt bundle uploaded', function () {
    $badFile = $this->tempDir . '/bad.tar.gz';
    file_put_contents($badFile, 'not-a-valid-tar-gz');

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['action'] = 'import_upload';
    $_FILES['import_file'] = [
        'name' => 'bad.tar.gz',
        'type' => 'application/gzip',
        'tmp_name' => $badFile,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($badFile),
    ];

    $controller = new AdminController($this->site);

    ob_start();
    $controller->config();
    ob_get_clean();

    expect(isset($_SESSION['import_error_message']))->toBeTrue();
});
