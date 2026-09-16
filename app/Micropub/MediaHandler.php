<?php

declare(strict_types=1);

namespace Indieinabox\Micropub;

use Indieinabox\Site\Site;

/**
 * Class MediaHandler
 *
 * Handles file uploads to the Micropub media endpoint (/micropub/media).
 */
class MediaHandler
{
    /**
     * @var array<int, string> Allowed media file extensions.
     */
    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'mp4', 'mp3', 'ogg', 'wav', 'webm', 'pdf'
    ];

    /**
     * Processes an uploaded media file, moves it to the media storage, and returns its public URL.
     *
     * @param Site $site Global site configuration.
     * @param array<string, mixed> $file Uploaded file information ($_FILES['file']).
     * @param ?callable $mover Optional callback to move uploaded file: fn(string $src, string $dst): bool.
     * @return array{status: int, headers?: array<string, string>, error?: string, error_description?: string}
     */
    public static function handleUpload(Site $site, array $file, ?callable $mover = null): array
    {
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [
                'status' => 400,
                'error' => 'Bad Request',
                'error_description' => 'No file uploaded or upload error.',
            ];
        }

        $fileName = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return [
                'status' => 400,
                'error' => 'Bad Request',
                'error_description' => 'Invalid or unsupported file extension.',
            ];
        }

        $baseFilename = date('dHis');
        $year = date('Y');
        $month = date('m');

        $contentDir = rtrim($site->paths->contentDir, DIRECTORY_SEPARATOR);
        $mediaDir = $contentDir . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;

        if (!is_dir($mediaDir)) {
            @mkdir($mediaDir, 0777, true);
        }

        $filename = $baseFilename . '.' . $ext;
        $counter = 1;
        while (file_exists($mediaDir . DIRECTORY_SEPARATOR . $filename)) {
            $newBase = (string) ((int) $baseFilename + $counter);
            $filename = $newBase . '.' . $ext;
            $counter++;
        }

        $destPath = $mediaDir . DIRECTORY_SEPARATOR . $filename;
        $tmpName = (string) ($file['tmp_name'] ?? '');

        $moved = $mover !== null ? $mover($tmpName, $destPath) : move_uploaded_file($tmpName, $destPath);
        if (!$moved) {
            return [
                'status' => 500,
                'error' => 'Server Error',
                'error_description' => 'Could not save uploaded file.',
            ];
        }

        $fileUrl = rtrim($site->fqdn ?? '', '/') . '/media/' . $year . '/' . $month . '/' . $filename;

        return [
            'status' => 201,
            'headers' => ['Location' => $fileUrl],
        ];
    }
}
