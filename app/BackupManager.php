<?php

declare(strict_types=1);

namespace Indieinabox;

class BackupManager {
    private Site $site;
    
    public function __construct(Site $site) {
        $this->site = $site;
    }
    
    public function run(bool $skipContent = false, bool $skipMedia = false): void {
        $base = rtrim($this->site->paths->baseDir, DIRECTORY_SEPARATOR);
        $dataDir = Database::$dataDir;
        
        $config = $this->site->config;
        
        // Defaults from config, override with CLI flags
        if (!$skipContent) {
            $skipContent = !empty($config['backup_skip_content']);
        }
        if (!$skipMedia) {
            $skipMedia = !empty($config['backup_skip_media']);
        }
        
        $destDir = $config['backup_dir'] ?? '../backup';
        
        // Resolve absolute path for destination
        if (!str_starts_with($destDir, '/')) {
            $destDir = $base . DIRECTORY_SEPARATOR . $destDir;
        }
        
        if (!is_dir($destDir)) {
            echo "Creating backup directory: {$destDir}\n";
            @mkdir($destDir, 0755, true);
        }
        
        $backupName = 'backup_' . date('Y-m-d_H-i-s') . '.tar.gz';
        $backupFile = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $backupName;
        
        echo "Generating backup archive: {$backupName}...\n";
        
        $itemsToBackup = [];
        
        // Content
        if (!$skipContent) {
            $contentDir = $this->site->paths->contentDir;
            if (is_dir($base . DIRECTORY_SEPARATOR . $contentDir)) {
                $itemsToBackup[] = $contentDir;
            }
        }
        
        // Data dir
        $relDataDir = str_replace($base . DIRECTORY_SEPARATOR, '', $dataDir);
        if (is_dir($dataDir)) {
            $itemsToBackup[] = $relDataDir;
        }
        
        // public_media
        if (!$skipMedia) {
            if (is_dir($base . DIRECTORY_SEPARATOR . 'public_media')) {
                $itemsToBackup[] = 'public_media';
            }
            $outputMedia = $this->site->paths->outputDirMedia ?? '';
            if ($outputMedia && $outputMedia !== 'public_media' && is_dir($base . DIRECTORY_SEPARATOR . $outputMedia)) {
                $itemsToBackup[] = $outputMedia;
            }
        }
        
        // Config files
        if (file_exists($base . DIRECTORY_SEPARATOR . '.env')) {
            $itemsToBackup[] = '.env';
        }
        if (file_exists($base . DIRECTORY_SEPARATOR . '.config.php')) {
            $itemsToBackup[] = '.config.php';
        }
        
        if (empty($itemsToBackup)) {
            echo "Nothing to backup.\n";
            return;
        }
        
        $escapedItems = array_map('escapeshellarg', $itemsToBackup);
        
        $cmd = "tar -czf " . escapeshellarg($backupFile) . " -C " . escapeshellarg($base) . " " . implode(" ", $escapedItems) . " 2>&1";
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "Backup successfully created at: {$backupFile}\n";
            $this->rotateBackups($destDir, (int)($config['backup_limit'] ?? 5));
        } else {
            echo "Error creating backup. Return code: {$returnCode}\n";
            echo implode("\n", $output) . "\n";
        }
    }
    
    private function rotateBackups(string $destDir, int $limit): void {
        if ($limit < 1) $limit = 1;
        
        $files = glob(rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'backup_*.tar.gz');
        if ($files === false) return;
        
        // Sort descending
        rsort($files);
        
        $toDelete = array_slice($files, $limit);
        
        foreach ($toDelete as $file) {
            if (is_file($file)) {
                echo "Deleting old backup: " . basename($file) . "\n";
                @unlink($file);
            }
        }
    }
}
