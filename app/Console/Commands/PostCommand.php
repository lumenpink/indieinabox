<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Core\Database;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\SiteBuilder;

/**
 * Command to compose and publish notes with attached media via CLI.
 */
class PostCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'post';
    }

    public function getDescription(): string
    {
        return 'Creates and publishes a new note with optional media attachments.';
    }

    public function getUsage(): string
    {
        return 'php indieinabox.php post create --text <content> [--media <file>]';
    }

    public function execute(array $argv): int
    {
        $subcommand = $argv[2] ?? '';
        if ($subcommand === 'create') {
            $text = $this->getOption($argv, 'text');
            $media = $this->getOption($argv, 'media');

            if (!$text) {
                echo "Error: --text is required.\n";
                return 1;
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
                if (!$ext) {
                    $ext = 'jpg';
                }
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
                    if (preg_match('/\.(mp4|webm|mov)$/i', $mp)) {
                        $content .= "<video src=\"{$mp}\" controls></video>\n";
                    } elseif (preg_match('/\.(mp3|ogg|wav)$/i', $mp)) {
                        $content .= "<audio src=\"{$mp}\" controls></audio>\n";
                    } else {
                        $content .= "![]({$mp})\n";
                    }
                }
            }

            file_put_contents($postPath, $content);
            echo "Post created at $postPath\n";

            $builder = new SiteBuilder($this->site);
            $builder->build();
            echo "Site rebuilt.\n";
            return 0;
        }

        echo "Usage: post create --text <content> [--media <file>]\n";
        return 1;
    }
}
