<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Database;

/**
 * Command to manage identity, profile bio, avatar, and banner images via CLI.
 */
class ProfileCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'profile';
    }

    public function getDescription(): string
    {
        return 'Configures site author profile, bio, avatar, and background banners.';
    }

    public function getUsage(): string
    {
        return "Usage:\n" .
               "  php indieinabox.php profile edit --username <name> --name <display_name> --bio <bio>\n" .
               "  php indieinabox.php profile media --avatar <path> --background <path>";
    }

    public function execute(array $argv): int
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
            return 0;
        }

        if ($subcommand === 'media') {
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
            return 0;
        }

        echo $this->getUsage() . "\n";
        return 1;
    }

    private function resizeImage(string $src, string $dest, int $maxWidth, int $maxHeight): void
    {
        $info = @getimagesize($src);
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

        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }

        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagepng($newImage, $dest, 9);
    }
}
