<?php
declare(strict_types=1);

namespace Indieinabox;

class LinkChecker
{
    private Site $site;
    private array $errors = [];

    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    public function run(): void
    {
        $base = rtrim($this->site->paths->baseDir, DIRECTORY_SEPARATOR);
        $htmlDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirHtml;
        
        if (!is_dir($htmlDir)) {
            echo "HTML output directory not found. Please build the site first.\n";
            exit(1);
        }

        echo "Scanning HTML files in {$this->site->paths->outputDirHtml}...\n";
        
        $files = $this->getHtmlFiles($htmlDir);
        $links = [];
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $extracted = $this->extractLinks($content);
            $relFile = str_replace($htmlDir, '', $file);
            foreach ($extracted as $link) {
                if (!isset($links[$link])) {
                    $links[$link] = [];
                }
                $links[$link][] = $relFile;
            }
        }
        
        echo "Found " . count($links) . " unique links to check.\n";
        
        $externalLinks = [];
        $internalLinks = [];
        
        foreach ($links as $link => $sources) {
            if (strpos($link, 'http://') === 0 || strpos($link, 'https://') === 0) {
                // Ignore localhost links in compiled static check
                if (strpos($link, 'http://localhost') !== 0) {
                    $externalLinks[$link] = $sources;
                }
            } elseif (strpos($link, 'mailto:') === 0 || strpos($link, 'tel:') === 0 || strpos($link, '#') === 0 || strpos($link, 'data:') === 0) {
                // Ignore special schemes
            } else {
                $internalLinks[$link] = $sources;
            }
        }

        echo "Checking " . count($internalLinks) . " internal links...\n";
        $this->checkInternalLinks($internalLinks, $base, $htmlDir);

        echo "Checking " . count($externalLinks) . " external links...\n";
        $this->checkExternalLinks($externalLinks);

        if (count($this->errors) > 0) {
            echo "\n❌ Found " . count($this->errors) . " broken links:\n";
            foreach ($this->errors as $err) {
                echo "  - {$err['link']} (Status: {$err['status']})\n";
                $uniqueSources = array_unique($err['sources']);
                $displaySources = array_slice($uniqueSources, 0, 3);
                echo "    Found in: " . implode(', ', $displaySources) . (count($uniqueSources) > 3 ? ' and ' . (count($uniqueSources) - 3) . ' more...' : '') . "\n";
            }
            exit(1);
        } else {
            echo "\n✅ All links are valid!\n";
        }
    }

    private function getHtmlFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'html') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    private function extractLinks(string $html): array
    {
        $links = [];
        if (preg_match_all('/(?:href|src)="([^"]+)"/i', $html, $matches)) {
            foreach ($matches[1] as $link) {
                $links[] = $link;
            }
        }
        return array_unique($links);
    }

    private function checkInternalLinks(array $links, string $base, string $htmlDir): void
    {
        $dynamicRoutes = [
            '/auth', '/token', '/micropub', '/microsub', '/admin',
            '/actor', '/inbox', '/outbox', '/cron', '/.well-known/'
        ];

        foreach ($links as $link => $sources) {
            $path = preg_replace('/[?#].*$/', '', $link);
            if ($path === '' || $path === '/') {
                $path = '/index.html';
            }

            // Ignore dynamic endpoints that don't exist as static files
            $isDynamic = false;
            foreach ($dynamicRoutes as $route) {
                if (strpos($path, $route) === 0) {
                    $isDynamic = true;
                    break;
                }
            }
            if ($isDynamic) {
                continue;
            }

            if (strpos($path, '/media/') === 0) {
                $target = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirMedia . substr($path, 6);
            } else {
                $path = urldecode($path);
                
                // If the link is relative and we have sources, resolve it relative to the first source file
                if (strpos($path, '/') !== 0 && count($sources) > 0) {
                    $sourceFile = $sources[0];
                    $sourceDir = dirname($sourceFile);
                    if ($sourceDir === '.') $sourceDir = '';
                    $resolvedPath = $sourceDir . '/' . $path;
                    
                    // Resolve ../ and ./
                    $parts = explode('/', $resolvedPath);
                    $absolutes = [];
                    foreach ($parts as $part) {
                        if ('' === $part || '.' === $part) continue;
                        if ('..' === $part) {
                            array_pop($absolutes);
                        } else {
                            $absolutes[] = $part;
                        }
                    }
                    $path = '/' . implode('/', $absolutes);
                }

                $target = $htmlDir . $path;
                
                if (is_dir($target)) {
                    $target = rtrim($target, '/') . '/index.html';
                } elseif (!is_file($target) && is_file($target . '.html')) {
                    $target = $target . '.html';
                }
            }

            if (!file_exists($target)) {
                $this->errors[] = [
                    'link' => $link,
                    'status' => '404 File Not Found',
                    'sources' => $sources
                ];
            }
        }
    }

    private function checkExternalLinks(array $links): void
    {
        if (!function_exists('curl_multi_init')) {
            echo "Warning: cURL extension not loaded. Skipping external links.\n";
            return;
        }

        $mh = curl_multi_init();
        $curlHandles = [];
        $batches = array_chunk(array_keys($links), 20);

        foreach ($batches as $batch) {
            foreach ($batch as $url) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                $userAgent = 'Indieinabox LinkChecker/1.0';
                curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
                curl_multi_add_handle($mh, $ch);
                $curlHandles[$url] = $ch;
            }

            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh);
            } while ($running > 0);

            foreach ($batch as $url) {
                $ch = $curlHandles[$url];
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                
                if ($code < 200 || $code >= 400) {
                    // Retry with GET
                    $chGet = curl_init($url);
                    curl_setopt($chGet, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chGet, CURLOPT_TIMEOUT, 10);
                    curl_setopt($chGet, CURLOPT_FOLLOWLOCATION, true);
                    $userAgent = 'Indieinabox LinkChecker/1.0';
                    curl_setopt($chGet, CURLOPT_USERAGENT, $userAgent);
                    curl_setopt($chGet, CURLOPT_HEADER, true);
                    curl_setopt($chGet, CURLOPT_NOBODY, false);
                    $range = '0-100';
                    curl_setopt($chGet, CURLOPT_RANGE, $range);
                    curl_exec($chGet);
                    $getCode = curl_getinfo($chGet, CURLINFO_HTTP_CODE);
                    curl_close($chGet);

                    // Accept 530 (Cloudflare Anti-bot)
                    if (($getCode < 200 || $getCode >= 400) && $getCode !== 530 && $code !== 530) {
                        $this->errors[] = [
                            'link' => $url,
                            'status' => $getCode > 0 ? (string)$getCode : 'Connection Error',
                            'sources' => $links[$url]
                        ];
                    }
                }
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
            $curlHandles = [];
        }
        curl_multi_close($mh);
    }
}
