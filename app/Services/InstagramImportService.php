<?php
declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Site\Site;
use Throwable;

class InstagramImportService
{
    private Site $site;
    private string $contentDir;
    private string $metaInboxDir;
    private string $stateFile;

    public function __construct(Site $site)
    {
        $this->site = $site;
        $configContentDir = (string)($site->config['contentdir'] ?? '../content');
        if (str_starts_with($configContentDir, '/')) {
            $this->contentDir = rtrim($configContentDir, '/');
        } else {
            $this->contentDir = rtrim($site->paths->baseDir . '/' . $configContentDir, '/');
        }

        $this->metaInboxDir = $this->contentDir . '/.meta_inbox';
        $dataDir = $site->paths->baseDir . '/data/indieinabox-data';
        if (!is_dir($dataDir)) {
            $dataDir = $site->paths->baseDir . '/data';
        }
        $this->stateFile = $dataDir . '/.instagram_import_state.json';
    }

    public function process(): void
    {
        if (!is_dir($this->metaInboxDir)) {
            return;
        }

        $state = [];
        if (file_exists($this->stateFile)) {
            $state = json_decode((string)file_get_contents($this->stateFile), true) ?: [];
        }

        $folders = @scandir($this->metaInboxDir);
        if (!$folders) return;

        $targetFotosDir = $this->contentDir . '/fotos';
        if (!is_dir($targetFotosDir) && is_dir($this->contentDir . '/97_floranda/fotos')) {
            $targetFotosDir = $this->contentDir . '/97_floranda/fotos';
        }
        @mkdir($targetFotosDir, 0755, true);

        foreach ($folders as $folder) {
            if ($folder === '.' || $folder === '..' || !str_starts_with($folder, 'instagram-')) {
                continue;
            }

            if (isset($state['_processed_folders'][$folder])) {
                continue;
            }

            $folderPath = $this->metaInboxDir . '/' . $folder;
            echo "[InstagramImport] Processing folder: {$folder}\n";

            // Processa posts.json
            foreach (['your_instagram_activity/media/posts.json', 'your_instagram_activity/media/posts_1.json'] as $rel) {
                $pPath = $folderPath . '/' . $rel;
                if (!file_exists($pPath)) continue;

                $data = json_decode((string)file_get_contents($pPath), true);
                if (!is_array($data)) continue;

                $posts = isset($data['label_values']) ? [$data] : (isset($data[0]) ? $data : [$data]);

                foreach ($posts as $post) {
                    $mediaList = $post['media'] ?? [];
                    $caption = $this->decodeMetaText($post['title'] ?? '');
                    $ts = (int)($post['creation_timestamp'] ?? 0);

                    // Novo formato Meta: label_values
                    if (isset($post['label_values']) && is_array($post['label_values'])) {
                        foreach ($post['label_values'] as $lv) {
                            $label = $lv['label'] ?? '';
                            if ($label === 'Media' && !empty($lv['media'])) {
                                $mediaList = $lv['media'];
                            }
                            if ($label === 'Caption' && !empty($lv['value'])) {
                                $caption = $this->decodeMetaText($lv['value']);
                            }
                            if ($label === 'Creation time' && !empty($lv['timestamp_value'])) {
                                if ($ts <= 0) $ts = (int)$lv['timestamp_value'];
                            }
                        }
                    }

                    if (empty($mediaList)) continue;
                    if ($ts <= 0 && isset($mediaList[0]['creation_timestamp'])) {
                        $ts = (int)$mediaList[0]['creation_timestamp'];
                    }
                    if ($caption === '' && isset($mediaList[0]['title'])) {
                        $caption = $this->decodeMetaText($mediaList[0]['title']);
                    }
                    if ($ts <= 0) continue;

                    // URIs de mídia
                    $uris = [];
                    foreach ($mediaList as $m) {
                        $u = $m['uri'] ?? '';
                        if ($u !== '') $uris[] = $u;
                    }
                    if (empty($uris)) continue;

                    $id = md5($ts . '_' . implode(',', $uris));
                    if (isset($state[$id])) {
                        continue;
                    }

                    // Prepara pasta do post no destino
                    $postDir = $targetFotosDir . '/' . $ts;
                    @mkdir($postDir, 0755, true);
                    $mdFile = $postDir . '/' . $ts . '.md';

                    $copiedFiles = [];
                    foreach ($uris as $srcRel) {
                        $srcFile = $folderPath . '/' . $srcRel;
                        $filename = basename($srcRel);
                        $destFile = $postDir . '/' . $filename;

                        if (file_exists($srcFile)) {
                            copy($srcFile, $destFile);
                            $copiedFiles[] = $filename;
                        }
                    }

                    // Tags e Frontmatter
                    preg_match_all('/#([\p{L}\p{N}_]+)/u', $caption, $tMatches);
                    $tags = array_values(array_unique($tMatches[1] ?? []));

                    $isoDate = date('c', $ts);
                    $frontmatter = "---\n";
                    $frontmatter .= "date: \"{$isoDate}\"\n";
                    if (!empty($tags)) {
                        $frontmatter .= "tags:\n";
                        foreach ($tags as $t) {
                            $frontmatter .= "  - \"{$t}\"\n";
                        }
                    }
                    if (!empty($copiedFiles)) {
                        $frontmatter .= "photo:\n";
                        foreach ($copiedFiles as $ph) {
                            $frontmatter .= "  - \"{$ph}\"\n";
                        }
                    }
                    $frontmatter .= "instagram_id: \"{$id}\"\n";
                    $frontmatter .= "---\n\n";

                    $body = "";
                    foreach ($copiedFiles as $cf) {
                        $body .= "![[{$cf}]]\n\n";
                    }
                    if ($caption !== '') {
                        $body .= $caption . "\n";
                    }

                    file_put_contents($mdFile, $frontmatter . $body);
                    echo "[InstagramImport] ✔ Created post at {$mdFile}\n";

                    $state[$id] = [
                        'timestamp' => $ts,
                        'path' => $mdFile,
                        'imported_at' => time()
                    ];
                }
            }

            $state['_processed_folders'][$folder] = time();
            @file_put_contents($this->stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    private function decodeMetaText(?string $text): string
    {
        if ($text === null || $text === '') return '';
        $converted = @mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }
        return $text;
    }
}
