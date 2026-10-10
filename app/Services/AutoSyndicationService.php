<?php
declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Site\Site;
use Indieinabox\Core\Database;
use Indieinabox\AtProto\BskySyndicationService;
use Indieinabox\Nostr\NostrSyndicationService;
use Indieinabox\Nostr\NostrService;
use Throwable;

class AutoSyndicationService
{
    private Site $site;
    private string $contentDir;

    public function __construct(Site $site)
    {
        $this->site = $site;
        $configContentDir = (string)($site->config['contentdir'] ?? '../content');
        if (str_starts_with($configContentDir, '/')) {
            $this->contentDir = rtrim($configContentDir, '/');
        } else {
            $this->contentDir = rtrim($site->paths->baseDir . '/' . $configContentDir, '/');
        }
    }

    public function process(): void
    {
        echo "[AutoSyndicate] Checking new content for syndication...\n";
        $kindsToScan = ['notas' => 'note', 'fotos' => 'photo', 'videos' => 'video', 'artigos' => 'article'];
        $now = time();
        $cutoff48h = $now - (48 * 3600);

        foreach ($kindsToScan as $folderName => $kind) {
            $scanPath = $this->contentDir . '/' . $folderName;
            if (!is_dir($scanPath)) {
                $florandaScan = $this->contentDir . '/97_floranda/' . $folderName;
                if (is_dir($florandaScan)) {
                    $scanPath = $florandaScan;
                } else {
                    continue;
                }
            }

            $this->scanDirectory($scanPath, $kind, $cutoff48h);
        }
    }

    private function scanDirectory(string $dir, string $kind, int $cutoff): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'md') {
                continue;
            }

            $filePath = $fileInfo->getPathname();
            $this->processFile($filePath, $kind, $cutoff);
        }
    }

    private function processFile(string $filePath, string $kind, int $cutoff): void
    {
        $raw = (string)@file_get_contents($filePath);
        if ($raw === '') return;

        if (!preg_match('/^---\r?\n(.*?)\r?\n---\r?\n(.*)$/s', $raw, $matches)) {
            return;
        }

        $frontmatterStr = $matches[1];
        $body = $matches[2];

        // 1. Regra no-syndicate: Se existir como campo ou tag, aborta imediatamente
        if (preg_match('/(^|\n)no-syndicate\s*:/i', $frontmatterStr) || 
            preg_match('/tags:.*?[-\s]+no-syndicate/is', $frontmatterStr) ||
            stripos($raw, '#no-syndicate') !== false) {
            return;
        }

        // 2. Extrai data e aplica janela de 48h
        $dateTimestamp = 0;
        if (preg_match('/(^|\n)date\s*:\s*["\']?([^"\n\r]+)["\']?/i', $frontmatterStr, $dMatch)) {
            $parsedDate = strtotime(trim($dMatch[2]));
            if ($parsedDate !== false && $parsedDate > 0) {
                $dateTimestamp = $parsedDate;
            }
        }
        if ($dateTimestamp <= 0) {
            $dateTimestamp = (int)@filemtime($filePath);
        }

        if ($dateTimestamp < $cutoff) {
            return; // Mais de 48h
        }

        // 3. Verifica alvos já sindicados
        $existingSyndications = [];
        if (preg_match('/(^|\n)syndication\s*:\s*\n((?:\s*-\s*[^\n]+\n?)*)/i', $frontmatterStr, $sMatch)) {
            preg_match_all('/-\s*([^\n\r]+)/', $sMatch[2], $items);
            foreach ($items[1] as $item) {
                $existingSyndications[] = trim($item, " \t\n\r\"'");
            }
        }

        // Verifica destinos permitidos (syndicate-to)
        $targets = ['bluesky', 'nostr'];
        if (preg_match('/(^|\n)syndicate-to\s*:\s*\[?([^\]\n\r]+)\]?/i', $frontmatterStr, $tMatch)) {
            $requested = array_map('trim', explode(',', strtolower($tMatch[2])));
            $targets = array_intersect($targets, $requested);
        }

        $newSyndications = [];

        // Sindicação Bluesky
        if (in_array('bluesky', $targets, true)) {
            $alreadyBluesky = false;
            foreach ($existingSyndications as $s) {
                if (str_contains($s, 'bsky.app')) {
                    $alreadyBluesky = true;
                    break;
                }
            }

            if (!$alreadyBluesky) {
                try {
                    $bsky = new BskySyndicationService($this->site);
                    if ($bsky->isConfigured()) {
                        echo "[AutoSyndicate] Syndicating to Bluesky: {$filePath}\n";
                        $url = $bsky->syndicateFile($filePath);
                        if ($url) {
                            $newSyndications[] = $url;
                            echo "[AutoSyndicate] ✔ Bluesky: {$url}\n";
                        }
                    }
                } catch (Throwable $e) {
                    echo "[AutoSyndicate] ✘ Bluesky error: " . $e->getMessage() . "\n";
                }
            }
        }

        // Sindicação Nostr
        if (in_array('nostr', $targets, true)) {
            $alreadyNostr = false;
            foreach ($existingSyndications as $s) {
                if (str_starts_with($s, 'nostr:') || str_contains($s, 'njump.me')) {
                    $alreadyNostr = true;
                    break;
                }
            }

            if (!$alreadyNostr) {
                try {
                    if (NostrService::isEnabled($this->site)) {
                        echo "[AutoSyndicate] Syndicating to Nostr: {$filePath}\n";
                        $nostr = new NostrSyndicationService($this->site);
                        $url = $nostr->syndicateFile($filePath);
                        if ($url) {
                            $newSyndications[] = $url;
                            echo "[AutoSyndicate] ✔ Nostr: {$url}\n";
                        }
                    }
                } catch (Throwable $e) {
                    echo "[AutoSyndicate] ✘ Nostr error: " . $e->getMessage() . "\n";
                }
            }
        }

        // Atualiza frontmatter no arquivo se houve novas sindicações
        if (!empty($newSyndications)) {
            $allSynd = array_unique(array_merge($existingSyndications, $newSyndications));
            $syndYaml = "syndication:\n";
            foreach ($allSynd as $u) {
                $syndYaml .= "  - " . $u . "\n";
            }

            if (preg_match('/(^|\n)syndication\s*:\s*\n((?:\s*-\s*[^\n]+\n?)*)/i', $frontmatterStr)) {
                $frontmatterStr = preg_replace('/(^|\n)syndication\s*:\s*\n((?:\s*-\s*[^\n]+\n?)*)/i', '$1' . $syndYaml, $frontmatterStr);
            } else {
                $frontmatterStr = rtrim($frontmatterStr) . "\n" . $syndYaml;
            }

            $newRaw = "---\n" . trim($frontmatterStr) . "\n---\n" . $body;
            @file_put_contents($filePath, $newRaw);
        }
    }
}
