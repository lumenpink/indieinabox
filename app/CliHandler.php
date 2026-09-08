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
        
        imagedestroy($image);
        imagedestroy($newImage);
    }
}
