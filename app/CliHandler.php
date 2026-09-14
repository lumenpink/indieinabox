<?php

declare(strict_types=1);

namespace Indieinabox;

class CliHandler
{
    private Site $site;

    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    private function getOption(array $argv, string $longOpt): ?string
    {
        foreach ($argv as $i => $arg) {
            if ($arg === "--{$longOpt}") {
                return $argv[$i + 1] ?? null;
            }
        }
        return null;
    }

    public function handleProfile(array $argv): void
    {
        $subcommand = $argv[2] ?? '';
        if ($subcommand === 'edit') {
            $username = $this->getOption($argv, 'username');
            $name = $this->getOption($argv, 'name');
            $bio = $this->getOption($argv, 'bio');

            if ($username) {
                Database::saveSetting('activitypub_handle', $username);
                echo "Username updated to: $username\n";
            }
            if ($name) {
                Database::saveSetting('sitename', $name);
                Database::saveSetting('author', $name);
                echo "Name updated to: $name\n";
            }
            if ($bio) {
                Database::saveSetting('activitypub_bio', $bio);
                echo "Bio updated.\n";
            }
        } elseif ($subcommand === 'media') {
            $avatar = $this->getOption($argv, 'avatar');
            $background = $this->getOption($argv, 'background');

            $publicMediaDir = Database::$dataDir . '/../public_media';
            if (!is_dir($publicMediaDir)) {
                @mkdir($publicMediaDir, 0755, true);
            }

            if ($avatar && file_exists($avatar)) {
                $dest = $publicMediaDir . '/avatar.png';
                $this->resizeImage($avatar, $dest, 400, 400);
                Database::saveSetting('activitypub_avatar', '/media/avatar.png');
                echo "Avatar updated.\n";
            }
            if ($background && file_exists($background)) {
                $dest = $publicMediaDir . '/background.png';
                $this->resizeImage($background, $dest, 1500, 500);
                Database::saveSetting('activitypub_background', '/media/background.png');
                echo "Background updated.\n";
            }
        } else {
            echo "Usage:\n";
            echo "  profile edit --username <name> --name <display_name> --bio <bio>\n";
            echo "  profile media --avatar <path> --background <path>\n";
        }
    }

    public function handlePost(array $argv): void
    {
        $subcommand = $argv[2] ?? '';
        if ($subcommand === 'create') {
            $text = $this->getOption($argv, 'text');
            $media = $this->getOption($argv, 'media');

            if (!$text) {
                echo "Error: --text is required.\n";
                return;
            }

            $date = date('Y-m-d-H-i-s');
            $contentDir = Database::$dataDir . '/../content';
            
            $mediaPaths = [];
            if ($media && file_exists($media)) {
                $mediaDir = $contentDir . '/media';
                if (!is_dir($mediaDir)) {
                    @mkdir($mediaDir, 0755, true);
                }
                $ext = pathinfo($media, PATHINFO_EXTENSION);
                if (!$ext) $ext = 'jpg';
                $destName = $date . '.' . $ext;
                $destPath = $mediaDir . '/' . $destName;
                copy($media, $destPath);
                $mediaPaths[] = '/media/' . $destName;
            }

            $postPath = $contentDir . '/notes/' . $date . '.md';
            if (!is_dir(dirname($postPath))) {
                @mkdir(dirname($postPath), 0755, true);
            }

            $content = $text;
            if (!empty($mediaPaths)) {
                $content .= "\n\n";
                foreach ($mediaPaths as $mp) {
                    // Check if image or video
                    if (preg_match('/\.(mp4|webm|mov)$/i', $mp)) {
                        $content .= "<video src=\"{$mp}\" controls></video>\n";
                    } else if (preg_match('/\.(mp3|ogg|wav)$/i', $mp)) {
                        $content .= "<audio src=\"{$mp}\" controls></audio>\n";
                    } else {
                        $content .= "![]({$mp})\n";
                    }
                }
            }

            file_put_contents($postPath, $content);
            echo "Post created at $postPath\n";

            // Trigger build
            $builder = new \Indieinabox\SiteBuilder($this->site);
            $builder->build();
            echo "Site rebuilt.\n";
        } else {
            echo "Usage: post create --text <content> [--media <file>]\n";
        }
    }

    private function resizeImage(string $src, string $dest, int $maxWidth, int $maxHeight): void
    {
        $info = getimagesize($src);
        if (!$info) {
            copy($src, $dest);
            return;
        }

        $width = $info[0];
        $height = $info[1];
        $type = $info[2];

        $ratio = min($maxWidth / $width, $maxHeight / $height);
        if ($ratio >= 1) {
            copy($src, $dest);
            return;
        }

        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        $image = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($src);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($src);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($src);
                break;
            case IMAGETYPE_WEBP:
                $image = imagecreatefromwebp($src);
                break;
        }

        if (!$image) {
            copy($src, $dest);
            return;
        }

        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }
        
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagepng($newImage, $dest, 9);
    }

    public function handleSetup(array $argv): void
    {
        $password = $this->getOption($argv, 'password');
        $name = $this->getOption($argv, 'name');
        $fqdn = $this->getOption($argv, 'fqdn');

        if (!$password) {
            echo "Enter admin password: ";
            $password = trim(fgets(STDIN));
        }
        if (!$name) {
            echo "Enter site name: ";
            $name = trim(fgets(STDIN));
        }
        if (!$fqdn) {
            echo "Enter FQDN (e.g. example.com): ";
            $fqdn = trim(fgets(STDIN));
        }

        if ($password && $name && $fqdn) {
            Database::saveSetting('admin_password', password_hash($password, PASSWORD_DEFAULT));
            Database::saveSetting('sitename', $name);
            Database::saveSetting('fqdn', $fqdn);
            // Default author to site name
            Database::saveSetting('author', $name);
            
            echo "Setup complete.\n";
        } else {
            echo "Setup failed. All fields are required.\n";
        }
    }

    public function handleConfig(array $argv): void
    {
        $subcommand = $argv[2] ?? '';
        if ($subcommand === 'set') {
            $key = $this->getOption($argv, 'key');
            $value = $this->getOption($argv, 'value');

            if (!$key || $value === null) {
                echo "Usage: config set --key <key> --value <value>\n";
                return;
            }

            Database::saveSetting($key, $value);
            echo "Config '$key' updated.\n";
        } elseif ($subcommand === 'get') {
            $key = $this->getOption($argv, 'key');

            if (!$key) {
                echo "Usage: config get --key <key>\n";
                return;
            }

            $val = Database::getSetting($key);
            echo "Config '$key': " . ($val ?? 'null') . "\n";
        } else {
            echo "Usage:\n";
            echo "  config set --key <key> --value <value>\n";
            echo "  config get --key <key>\n";
        }
    }

    /**
     * Handles the 'test-webmention' CLI command to test endpoint discovery and ping delivery.
     *
     * @param array<int, string> $argv
     * @return void
     */
    public function handleTestWebmention(array $argv): void
    {
        if (in_array('--validate-hcard', $argv, true)) {
            $this->handleValidateHcard($argv);
            return;
        }

        $targetUrl = null;
        $sourceUrl = $this->getOption($argv, 'source');

        foreach ($argv as $i => $arg) {
            if ($i <= 1 || $arg === '--source') {
                continue;
            }
            if (isset($argv[$i - 1]) && $argv[$i - 1] === '--source') {
                continue;
            }
            if (strpos($arg, '--') !== 0) {
                $targetUrl = $arg;
                break;
            }
        }

        if (!$targetUrl) {
            echo "Usage:\n";
            echo "  php indieinabox.php test-webmention <target-url> [--source <source-url>]\n";
            echo "  php indieinabox.php test-webmention --validate-hcard [url-or-file]\n";
            return;
        }

        echo "Checking target: $targetUrl\n";
        $discovery = WebmentionSender::discoverEndpointDetails($targetUrl);

        if ($discovery['effective_url'] !== $targetUrl) {
            echo "Redirected to: " . $discovery['effective_url'] . "\n";
        }
        echo "HTTP Status: " . $discovery['http_code'] . "\n";

        if ($discovery['endpoint']) {
            $methodDesc = match ($discovery['method']) {
                'http_header' => 'HTTP Link Header',
                'html_link' => 'HTML <link rel="webmention">',
                'html_a' => 'HTML <a rel="webmention">',
                default => 'Unknown',
            };
            echo "Discovered Webmention Endpoint: " . $discovery['endpoint'] . "\n";
            echo "Discovery Method: " . $methodDesc . "\n";

            if ($sourceUrl) {
                echo "\nSending Webmention ping...\n";
                echo "Source: $sourceUrl\n";
                echo "Target: $targetUrl\n";
                $result = WebmentionSender::sendWebmention($discovery['endpoint'], $sourceUrl, $targetUrl);
                echo "Response Code: " . $result['http_code'] . "\n";
                if ($result['success']) {
                    echo "Result: SUCCESS (Webmention accepted)\n";
                } else {
                    $err = $result['error'] ?? ('HTTP ' . $result['http_code']);
                    echo "Result: FAILED ($err)\n";
                }
                if (!empty($result['response'])) {
                    echo "Response Body:\n" . trim(substr($result['response'], 0, 500)) . "\n";
                }
            } else {
                echo "\nTip: To send a live webmention ping to this endpoint, pass --source <source-url>\n";
            }
        } else {
            echo "No Webmention endpoint found for $targetUrl\n";
        }
    }

    /**
     * Validates the presence and completeness of an h-card for IndieWebify.me Level 1.
     *
     * @param array<int, string> $argv
     * @return void
     */
    private function handleValidateHcard(array $argv): void
    {
        $target = null;
        foreach ($argv as $i => $arg) {
            if ($i <= 1 || $arg === '--validate-hcard') {
                continue;
            }
            if (strpos($arg, '--') !== 0) {
                $target = $arg;
                break;
            }
        }

        $html = '';
        $sourceDesc = '';
        if ($target && (strpos($target, 'http://') === 0 || strpos($target, 'https://') === 0)) {
            $sourceDesc = $target;
            $html = (string) @file_get_contents($target);
        } elseif ($target && file_exists($target)) {
            $sourceDesc = $target;
            $html = (string) file_get_contents($target);
        } else {
            $homeFile = $this->site->paths->outputDirHtml . '/index.html';
            if (file_exists($homeFile)) {
                $sourceDesc = $homeFile;
                $html = (string) file_get_contents($homeFile);
            } else {
                $sourceDesc = 'Default Theme Header';
                $html = \Indieinabox\Theme\ThemeData::getHCard($this->site);
            }
        }

        if (trim($html) === '') {
            echo "Error: Could not load HTML to validate h-card from " . ($sourceDesc ?: 'default source') . "\n";
            return;
        }

        echo "Validating IndieWebify.me Level 1 (h-card) on: $sourceDesc\n\n";

        $parsed = function_exists('\Mf2\parse') ? \Mf2\parse($html, 'http://localhost') : ['items' => []];
        $hcard = null;
        foreach ($parsed['items'] ?? [] as $item) {
            if (in_array('h-card', $item['type'] ?? [], true)) {
                $hcard = $item;
                break;
            }
        }

        if (!$hcard) {
            echo "[FAILED] No element with class 'h-card' found.\n";
            return;
        }

        echo "[OK] Found 'h-card' microformat.\n";

        $props = $hcard['properties'] ?? [];
        $hasName = !empty($props['name'][0]);
        $hasUrl = !empty($props['url'][0]);
        $hasPhoto = !empty($props['photo'][0]);
        $hasNote = !empty($props['note'][0]);

        $nameVal = is_array($props['name'][0] ?? null) ? ($props['name'][0]['value'] ?? '') : ($props['name'][0] ?? '');
        $urlVal = is_array($props['url'][0] ?? null) ? ($props['url'][0]['value'] ?? '') : ($props['url'][0] ?? '');
        $photoVal = is_array($props['photo'][0] ?? null) ? ($props['photo'][0]['value'] ?? '') : ($props['photo'][0] ?? '');
        $noteVal = is_array($props['note'][0] ?? null) ? ($props['note'][0]['value'] ?? '') : ($props['note'][0] ?? '');

        echo ($hasName ? "[OK] Name (p-name): " . $nameVal : "[WARN] Missing Name (p-name)") . "\n";
        echo ($hasUrl ? "[OK] URL (u-url): " . $urlVal : "[WARN] Missing URL (u-url)") . "\n";
        echo ($hasPhoto ? "[OK] Photo (u-photo): " . $photoVal : "[INFO] Photo (u-photo) not present (optional)") . "\n";
        echo ($hasNote ? "[OK] Note (p-note): " . $noteVal : "[INFO] Note (p-note) not present (optional)") . "\n";

        if ($hasName && $hasUrl) {
            echo "\nResult: PASS - IndieWebify.me Level 1 criteria satisfied!\n";
        } else {
            echo "\nResult: INCOMPLETE - IndieWebify.me Level 1 requires at least p-name and u-url.\n";
        }
    }
}

