<?php

declare(strict_types=1);

namespace Indieinabox;

class WebmentionHandler
{
    private Site $site;

    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method !== 'POST') {
            $this->sendHelpPage();
            return;
        }

        // It is a POST request, handle the Webmention
        $source = $_POST['source'] ?? null;
        $target = $_POST['target'] ?? null;

        if (empty($source) || empty($target)) {
            $this->sendResponse(400, 'Missing source or target parameters.');
            return;
        }

        // Validate URLs
        if (!filter_var($source, FILTER_VALIDATE_URL) || !filter_var($target, FILTER_VALIDATE_URL)) {
            $this->sendResponse(400, 'Invalid source or target URL format.');
            return;
        }

        // Validate target matches our domain/fqdn
        $targetHost = parse_url($target, PHP_URL_HOST);
        $siteHost = parse_url($this->site->metadata->fqdn ?? '', PHP_URL_HOST);

        if (empty($targetHost) || empty($siteHost) || strcasecmp($targetHost, $siteHost) !== 0) {
            $this->sendResponse(400, 'Target URL does not belong to this site.');
            return;
        }

        if (strcasecmp($source, $target) === 0) {
            $this->sendResponse(400, 'Source and target URLs cannot be identical.');
            return;
        }

        // Verify that target URL is a valid page on our site (exists in output dir)
        $targetPath = parse_url($target, PHP_URL_PATH) ?? '/';
        $sitePath = parse_url($this->site->metadata->fqdn ?? '', PHP_URL_PATH);
        if ($sitePath && $sitePath !== '/' && strpos($targetPath, $sitePath) === 0) {
            $targetPath = substr($targetPath, strlen($sitePath));
        }

        $base = $this->site->paths->baseDir;
        $outputDir = $this->site->paths->outputDir;

        $targetPathClean = str_replace('..', '', urldecode($targetPath));
        if ($targetPathClean === '' || $targetPathClean === '/') {
            $targetFile = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . 'index.html';
        } else {
            $targetFile = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . trim($targetPathClean, '/') . DIRECTORY_SEPARATOR . 'index.html';
            if (!file_exists($targetFile)) {
                $targetFile = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . trim($targetPathClean, '/');
            }
        }

        if (!file_exists($targetFile)) {
            $this->sendResponse(400, 'Target page not found on this site.');
            return;
        }

        // Verify link (check if source contains a link to target)
        $verificationResult = $this->verifySourceLink($source, $target);
        if (!$verificationResult['success']) {
            $this->sendResponse(400, 'Source page does not link to target page. Error: ' . ($verificationResult['message'] ?? ''));
            return;
        }

        // Save Webmention
        $this->saveWebmention($source, $target, $verificationResult['content'] ?? ['title' => '', 'text' => '']);

        $this->sendResponse(202, 'Webmention accepted and processed.');
    }

    /**
     * @param string $source
     * @param string $target
     * @return array{success: bool, message?: string, content?: array{title: string, text: string}}
     */
    public function verifySourceLink(string $source, string $target): array
    {
        $html = $this->fetchUrl($source);

        if ($html === false) {
            return [
                'success' => false,
                'message' => 'Unable to fetch source URL.'
            ];
        }

        // Verify HTML contains link to target
        $dom = new \DOMDocument();
        // Suppress HTML parsing warnings
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $links = $xpath->query('//a[@href]');
        $found = false;
        foreach ($links as $link) {
            if ($link instanceof \DOMElement) {
                $href = $link->getAttribute('href');
                if ($this->urlsMatch($href, $target, $source)) {
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            return [
                'success' => false,
                'message' => 'No link to target URL found on source page.'
            ];
        }

        $titleNode = $xpath->query('//title')->item(0);
        $title = $titleNode ? trim($titleNode->nodeValue) : '';

        $content = '';
        $entryContent = $xpath->query('//*[contains(@class, "e-content")]')->item(0);
        if ($entryContent) {
            $content = trim($entryContent->nodeValue);
        } else {
            $pNode = $xpath->query('//p')->item(0);
            if ($pNode) {
                $content = trim($pNode->nodeValue);
            }
        }

        if (strlen($content) > 300) {
            $content = substr($content, 0, 297) . '...';
        }

        return [
            'success' => true,
            'content' => [
                'title' => $title,
                'text' => $content
            ]
        ];
    }

    /**
     * Allows overriding the URL fetcher for test mocking.
     *
     * @param string $url
     * @return string|false
     */
    protected function fetchUrl(string $url)
    {
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: IndieinaboxWebmentionReceiver/0.1.0\r\nAccept: text/html\r\n",
                'timeout' => 5,
            ]
        ];
        $context = stream_context_create($options);
        return @file_get_contents($url, false, $context);
    }

    /**
     * Compare target and link href to check if they match (including relative links)
     *
     * @param string $href
     * @param string $target
     * @param string $source
     * @return bool
     */
    public function urlsMatch(string $href, string $target, string $source): bool
    {
        $normalize = function (string $url): string {
            $parts = parse_url($url);
            if (!$parts) {
                return $url;
            }
            $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'http';
            $host = isset($parts['host']) ? strtolower($parts['host']) : '';
            $port = isset($parts['port']) ? ':' . $parts['port'] : '';
            $path = $parts['path'] ?? '/';
            
            // Resolve relative path segments like .. and .
            $segments = explode('/', $path);
            $resolved = [];
            foreach ($segments as $segment) {
                if ($segment === '.' || $segment === '') {
                    continue;
                }
                if ($segment === '..') {
                    array_pop($resolved);
                } else {
                    $resolved[] = $segment;
                }
            }
            $path = '/' . implode('/', $resolved);
            if ($path !== '/') {
                $path = rtrim($path, '/');
            }
            $query = isset($parts['query']) ? '?' . $parts['query'] : '';
            return $scheme . '://' . $host . $port . $path . $query;
        };

        $targetNorm = $normalize($target);

        if (strncasecmp($href, 'http', 4) === 0) {
            return strcasecmp($normalize($href), $targetNorm) === 0;
        }

        // Relative URL
        $sourceParts = parse_url($source);
        $base = ($sourceParts['scheme'] ?? 'http') . '://' . ($sourceParts['host'] ?? '');
        if (isset($sourceParts['port'])) {
            $base .= ':' . $sourceParts['port'];
        }

        if (substr($href, 0, 1) === '/') {
            $resolved = $base . '/' . ltrim($href, '/');
        } else {
            $path = $sourceParts['path'] ?? '/';
            $dir = dirname($path);
            $dirClean = ($dir === '/' || $dir === '\\' || $dir === '.') ? '' : trim($dir, '/');
            if ($dirClean !== '') {
                $resolved = $base . '/' . $dirClean . '/' . ltrim($href, '/');
            } else {
                $resolved = $base . '/' . ltrim($href, '/');
            }
        }

        return strcasecmp($normalize($resolved), $targetNorm) === 0;
    }

    public function saveWebmention(string $source, string $target, array $meta): void
    {
        $db = \Indieinabox\Database::getDb();

        $targetPath = parse_url($target, PHP_URL_PATH) ?? '/';
        $sitePath = parse_url($this->site->metadata->fqdn ?? '', PHP_URL_PATH);
        if ($sitePath && $sitePath !== '/' && strpos($targetPath, $sitePath) === 0) {
            $targetPath = substr($targetPath, strlen($sitePath));
        }
        $slug = trim($targetPath, '/');
        if ($slug === '') {
            $slug = 'home';
        }
        $hash = md5($slug);

        $stmt = $db->prepare('SELECT payload_json FROM webmentions WHERE hash = :hash');
        $stmt->bindValue(':hash', $hash, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $existing = [];
        if ($row && isset($row['payload_json'])) {
            $data = json_decode($row['payload_json'], true);
            if (is_array($data)) {
                $existing = $data;
            }
        }

        $newMention = [
            'source' => $source,
            'target' => $target,
            'title' => $meta['title'] ?: 'Webmention from ' . (parse_url($source, PHP_URL_HOST) ?? 'external link'),
            'text' => $meta['text'],
            'date' => date('c'),
        ];

        // Filter duplicates
        $existing = array_filter($existing, function($mention) use ($source) {
            return $mention['source'] !== $source;
        });

        $existing[] = $newMention;

        $json = json_encode(array_values($existing), JSON_PRETTY_PRINT);

        if ($row) {
            $upd = $db->prepare('UPDATE webmentions SET payload_json = :json WHERE hash = :hash');
            $upd->bindValue(':hash', $hash, \PDO::PARAM_STR);
            $upd->bindValue(':json', $json, \PDO::PARAM_STR);
            $upd->execute();
        } else {
            $ins = $db->prepare('INSERT INTO webmentions (hash, payload_json) VALUES (:hash, :json)');
            $ins->bindValue(':hash', $hash, \PDO::PARAM_STR);
            $ins->bindValue(':json', $json, \PDO::PARAM_STR);
            $ins->execute();
        }
    }

    private function sendResponse(int $code, string $message): void
    {
        header('HTTP/1.1 ' . $code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => $code,
            'message' => $message
        ], JSON_PRETTY_PRINT);
    }

    private function sendHelpPage(): void
    {
        header('HTTP/1.1 200 OK');
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Webmention Endpoint</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
            <style>
                :root {
                    --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #311042 100%);
                    --card-bg: rgba(30, 41, 59, 0.7);
                    --accent: #eccb00;
                    --accent-glow: rgba(236, 203, 0, 0.4);
                    --text-primary: #f8fafc;
                    --text-secondary: #94a3b8;
                    --border: rgba(255, 255, 255, 0.08);
                    --input-bg: rgba(15, 23, 42, 0.6);
                    --input-focus: rgba(236, 203, 0, 0.15);
                }

                body {
                    font-family: 'Outfit', sans-serif;
                    background: var(--bg-gradient);
                    background-attachment: fixed;
                    color: var(--text-primary);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0;
                    padding: 2rem 1.5rem;
                    box-sizing: border-box;
                }

                .container {
                    backdrop-filter: blur(16px);
                    -webkit-backdrop-filter: blur(16px);
                    background: var(--card-bg);
                    border: 1px solid var(--border);
                    border-radius: 24px;
                    padding: 3rem;
                    max-width: 640px;
                    width: 100%;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5),
                                0 0 40px rgba(236, 203, 0, 0.05);
                    position: relative;
                    overflow: hidden;
                    transition: transform 0.3s ease, box-shadow 0.3s ease;
                }

                .container:hover {
                    box-shadow: 0 30px 60px -10px rgba(0, 0, 0, 0.6),
                                0 0 50px rgba(236, 203, 0, 0.1);
                }

                .container::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    background: linear-gradient(90deg, #eccb00, #ff8a00);
                }

                h1 {
                    font-size: 2.25rem;
                    font-weight: 800;
                    margin-top: 0;
                    margin-bottom: 0.75rem;
                    background: linear-gradient(90deg, #ffffff, #eccb00);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    letter-spacing: -0.025em;
                }

                .subtitle {
                    color: var(--text-secondary);
                    font-size: 1.1rem;
                    line-height: 1.6;
                    margin-bottom: 2rem;
                }

                .instruction-box {
                    background: rgba(15, 23, 42, 0.4);
                    border: 1px solid var(--border);
                    border-radius: 16px;
                    padding: 1.5rem;
                    margin-bottom: 2rem;
                }

                .instruction-box p {
                    margin: 0 0 1rem 0;
                    font-size: 0.95rem;
                    line-height: 1.5;
                    color: #e2e8f0;
                }

                .instruction-box p:last-child {
                    margin-bottom: 0;
                }

                .instruction-box ul {
                    margin: 0.5rem 0 0 0;
                    padding-left: 1.5rem;
                }

                .instruction-box li {
                    margin-bottom: 0.5rem;
                    font-size: 0.95rem;
                    color: var(--text-secondary);
                }

                .instruction-box li strong {
                    color: var(--text-primary);
                }

                code {
                    font-family: 'JetBrains Mono', monospace;
                    background: rgba(236, 203, 0, 0.1);
                    color: var(--accent);
                    padding: 0.2rem 0.4rem;
                    border-radius: 6px;
                    font-size: 0.9em;
                    border: 1px solid rgba(236, 203, 0, 0.2);
                }

                form {
                    display: flex;
                    flex-direction: column;
                    gap: 1.5rem;
                }

                .form-group {
                    display: flex;
                    flex-direction: column;
                    gap: 0.5rem;
                }

                label {
                    font-weight: 600;
                    font-size: 0.9rem;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    color: var(--text-secondary);
                }

                input[type="url"] {
                    font-family: inherit;
                    background: var(--input-bg);
                    border: 1px solid var(--border);
                    border-radius: 12px;
                    padding: 0.85rem 1rem;
                    font-size: 1rem;
                    color: var(--text-primary);
                    transition: all 0.2s ease;
                }

                input[type="url"]:focus {
                    outline: none;
                    border-color: var(--accent);
                    box-shadow: 0 0 0 4px var(--input-focus);
                    background: rgba(15, 23, 42, 0.8);
                }

                input[type="url"]::placeholder {
                    color: #475569;
                }

                button {
                    font-family: inherit;
                    background: linear-gradient(135deg, #eccb00 0%, #d8b600 100%);
                    color: #0f172a;
                    border: none;
                    padding: 1rem 1.5rem;
                    border-radius: 12px;
                    font-size: 1.1rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    box-shadow: 0 4px 12px var(--accent-glow);
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                }

                button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px var(--accent-glow);
                    background: linear-gradient(135deg, #fce029 0%, #eccb00 100%);
                }

                button:active {
                    transform: translateY(0);
                }

                .footer {
                    margin-top: 2.5rem;
                    text-align: center;
                    font-size: 0.85rem;
                    color: #475569;
                    border-top: 1px solid var(--border);
                    padding-top: 1.5rem;
                }

                .footer a {
                    color: var(--text-secondary);
                    text-decoration: none;
                    transition: color 0.2s ease;
                }

                .footer a:hover {
                    color: var(--accent);
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Webmention Endpoint</h1>
                <p class="subtitle">This endpoint allows external websites to notify this site when they link to its content.</p>
                
                <div class="instruction-box">
                    <p>To send a webmention programmatically, make an HTTP <code>POST</code> request with the following form-encoded parameters:</p>
                    <ul>
                        <li><code>source</code>: The absolute URL of your page referencing this site.</li>
                        <li><code>target</code>: The absolute URL of the page on this site being referenced.</li>
                    </ul>
                </div>

                <form action="" method="POST">
                    <div class="form-group">
                        <label for="source">Source URL</label>
                        <input type="url" name="source" id="source" required placeholder="https://yourdomain.com/posts/my-awesome-post">
                    </div>
                    <div class="form-group">
                        <label for="target">Target URL</label>
                        <input type="url" name="target" id="target" required value="<?= htmlspecialchars($this->site->metadata->fqdn ?? '') ?>" placeholder="https://example.com/post-slug">
                    </div>
                    <button type="submit">
                        Send Webmention
                    </button>
                </form>
                
                <div class="footer">
                    Powered by <a href="https://indieinabox.org" target="_blank" rel="noopener noreferrer">IndieInABox</a>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
