<?php

declare(strict_types=1);

namespace Indieinabox;

class MicropubHandler
{
    private Site $site;
    private IndieAuthHandler $authHandler;

    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->authHandler = new IndieAuthHandler($site);
    }

    public function handle(): void
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $requestUriClean = rtrim($requestUri, '/');

        // Verify Bearer Token
        $tokenData = $this->authHandler->validateBearerToken();
        if (!$tokenData) {
            $this->sendResponse(401, 'Unauthorized', 'Missing or invalid Bearer token.');
            return;
        }

        // Endpoint: /micropub/media
        if ($requestUriClean === '/micropub/media') {
            $this->handleMediaEndpoint($tokenData);
            return;
        }

        // Endpoint: /micropub
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'GET') {
            $this->handleGetRequest();
            return;
        }

        if ($method === 'POST') {
            $this->handlePostRequest($tokenData);
            return;
        }

        $this->sendResponse(405, 'Method Not Allowed', 'Unsupported HTTP method.');
    }

    protected function getRawInput(): string
    {
        return file_get_contents('php://input');
    }

    private function handleGetRequest(): void
    {
        $q = $_GET['q'] ?? '';
        if ($q === 'config') {
            $this->sendSuccessResponse(200, ['Content-Type' => 'application/json; charset=utf-8'], [
                'media-endpoint' => rtrim($this->site->fqdn ?? '', '/') . '/micropub/media',
                'syndicate-to' => []
            ]);
            return;
        }
        
        if ($q === 'syndicate-to') {
            $this->sendSuccessResponse(200, ['Content-Type' => 'application/json; charset=utf-8'], ['syndicate-to' => []]);
            return;
        }

        $this->sendResponse(400, 'Invalid Query', 'Unsupported q parameter.');
    }

    /**
     * @param array<string, mixed> $tokenData
     */
    private function handlePostRequest(array $tokenData): void
    {
        $scopes = explode(' ', $tokenData['scope'] ?? '');
        if (!in_array('create', $scopes)) {
            $this->sendResponse(403, 'Forbidden', 'The create scope is required.');
            return;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        $input = [];
        if ($contentType === 'application/json') {
            $json = $this->getRawInput();
            $data = json_decode($json, true) ?: [];
            if (!is_array($data)) {
                $this->sendResponse(400, 'Invalid JSON', 'Malformed JSON payload.');
                return;
            }
            
            $input['h'] = $data['type'][0] ?? 'entry';
            if (isset($data['type'])) {
                $input['h'] = str_replace('h-', '', $input['h']);
            }
            
            $properties = $data['properties'] ?? [];
            foreach ($properties as $key => $values) {
                if (is_array($values)) {
                    $input[$key] = count($values) === 1 ? $values[0] : $values;
                }
            }
        } else {
            // Form-urlencoded or multipart
            $input = $_POST;
        }

        // Action routing (delete, undelete, update not fully implemented yet)
        $action = $input['action'] ?? 'create';
        if ($action !== 'create') {
            $this->sendResponse(400, 'Not Supported', 'Only create action is supported for now.');
            return;
        }

        $this->createPost($input);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function createPost(array $input): void
    {
        $name = $input['name'] ?? null;
        $content = $input['content'] ?? '';
        if (is_array($content) && isset($content['html'])) {
            $content = $content['html']; // Simplified for now, should convert to md or save as html
        } elseif (is_array($content) && isset($content['value'])) {
            $content = $content['value'];
        }

        $slug = $input['mp-slug'] ?? ($name ? $this->slugify($name) : date('dHis'));
        $lang = $input['mp-language'] ?? ''; // e.g. 'pt' or 'en'
        $category = $input['category'] ?? [];
        if (!is_array($category) && !empty($category)) {
            $category = [$category];
        }

        // Determine kind
        $kind = 'note';
        if ($name) {
            $kind = 'article';
        }

        // Photo uploads sent with the post
        $photos = [];
        if (isset($input['photo'])) {
            $photos = is_array($input['photo']) ? $input['photo'] : [$input['photo']];
            if ($kind === 'note') {
                $kind = 'photo';
            }
        }

        // Generate Frontmatter
        $frontmatter = [];
        if ($name) {
            $frontmatter['title'] = $name;
        }
        $frontmatter['date'] = date('Y-m-d H:i:s');
        if (!empty($category)) {
            $frontmatter['tags'] = $category;
        }
        
        $yaml = "---\n";
        foreach ($frontmatter as $k => $v) {
            if (is_array($v)) {
                $yaml .= "$k:\n";
                foreach ($v as $item) {
                    $yaml .= "  - $item\n";
                }
            } else {
                $yaml .= "$k: \"$v\"\n";
            }
        }
        $yaml .= "---\n\n";
        
        // Append photos to content if provided
        foreach ($photos as $photo) {
            if (is_string($photo)) {
                if (strpos($content, $photo) === false) {
                    $yaml .= "![]($photo)\n\n";
                }
            }
        }

        $yaml .= $content;

        // Determine directory path
        $contentDir = rtrim($this->site->paths->contentDir, DIRECTORY_SEPARATOR);
        
        if ($lang && $lang !== $this->site->localization->defaultLang) {
            $contentDir .= DIRECTORY_SEPARATOR . $lang;
        }

        // We use kind/year/month logic
        $year = date('Y');
        $month = date('m');
        $dir = $contentDir . DIRECTORY_SEPARATOR . $kind . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $originalSlug = $slug;
        $counter = 1;
        while (file_exists($dir . DIRECTORY_SEPARATOR . $slug . '.md')) {
            if (is_numeric($originalSlug)) {
                $slug = (string)((int)$originalSlug + $counter);
            } else {
                $slug = $originalSlug . '-' . $counter;
            }
            $counter++;
        }

        $filePath = $dir . DIRECTORY_SEPARATOR . $slug . '.md';
        file_put_contents($filePath, $yaml);

        // Rebuild site asynchronously
        if (class_exists('\\Indieinabox\\ConfigHandler')) {
            $db = \Indieinabox\Database::getDb();
            $stmt = $db->query("SELECT 1 FROM inbox_queue WHERE type = 'build_site'");
            if (!$stmt->fetch()) {
                $insert = $db->prepare("INSERT INTO inbox_queue (type, payload_json, created_at) VALUES (?, ?, ?)");
                $insert->execute(['build_site', json_encode([]), time()]);
            }
        }

        // Build the created URL
        // Example: https://lumen.pink/pt/articles/2026/06/slug.html
        // (depends on the routing of Indieinabox, but roughly)
        $baseUrl = rtrim($this->site->fqdn ?? '', '/');
        $postUrl = $baseUrl . '/' . $kind . '/' . $year . '/' . $month . '/' . $slug . '.html';
        
        if ($lang && $lang !== $this->site->localization->defaultLang) {
            $postUrl = $baseUrl . '/' . $lang . '/' . $kind . '/' . $year . '/' . $month . '/' . $slug . '.html';
        }

        // Queue ActivityPub outbox message
        if (class_exists('\\Indieinabox\\ActivityPubHandler')) {
            $apHandler = new \Indieinabox\ActivityPubHandler($this->site);
            $apHandler->queueCreateActivity($postUrl, $content, $name);
        }

        $this->sendSuccessResponse(202, ['Location' => $postUrl]);
    }

    /**
     * @param array<string, mixed> $tokenData
     */
    private function handleMediaEndpoint(array $tokenData): void
    {
        $scopes = explode(' ', $tokenData['scope'] ?? '');
        if (!in_array('media', $scopes) && !in_array('create', $scopes)) {
            $this->sendResponse(403, 'Forbidden', 'The media or create scope is required.');
            return;
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->sendResponse(400, 'Bad Request', 'No file uploaded or upload error.');
            return;
        }

        $file = $_FILES['file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (empty($ext)) {
            $ext = 'bin';
        }

        $baseFilename = date('dHis');
        $year = date('Y');
        $month = date('m');

        $contentDir = rtrim($this->site->paths->contentDir, DIRECTORY_SEPARATOR);
        $mediaDir = $contentDir . DIRECTORY_SEPARATOR . 'media';
        $mediaDir .= DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;

        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0777, true);
        }

        $filename = $baseFilename . '.' . $ext;
        $counter = 1;
        while (file_exists($mediaDir . DIRECTORY_SEPARATOR . $filename)) {
            $newBase = (string)((int)$baseFilename + $counter);
            $filename = $newBase . '.' . $ext;
            $counter++;
        }

        $destPath = $mediaDir . DIRECTORY_SEPARATOR . $filename;
        if (!$this->moveUploadedFile($file['tmp_name'], $destPath)) {
            $this->sendResponse(500, 'Server Error', 'Could not save uploaded file.');
            return;
        }

        $fileUrl = rtrim($this->site->fqdn ?? '', '/') . '/media/' . $year . '/' . $month . '/' . $filename;

        $this->sendSuccessResponse(201, ['Location' => $fileUrl]);
    }

    protected function sendSuccessResponse(int $code, array $headers = [], $body = null): void
    {
        header('HTTP/1.1 ' . $code);
        foreach ($headers as $key => $value) {
            header($key . ': ' . $value);
        }
        if ($body !== null) {
            echo is_string($body) ? $body : json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }

    protected function sendResponse(int $code, string $error, string $description): void
    {
        header('HTTP/1.1 ' . $code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => $error,
            'error_description' => $description
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    protected function moveUploadedFile(string $tmpName, string $destPath): bool
    {
        return move_uploaded_file($tmpName, $destPath);
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        if (empty($text)) {
            return 'n-a';
        }
        return $text;
    }
}
