<?php
/** @var \Indieinabox\Page $page */

$db = null;
try {
    $db = \Indieinabox\Database::getDb();
} catch (\Exception $e) {
    // DB not available
}

if ($db) {
    $slug = trim($page->slug ?? 'home', '/');
    if ($slug === '') {
        $slug = 'home';
    }
    $hash = md5($slug);
    
    $dataDir = \Indieinabox\Database::$dataDir ?? (dirname(__DIR__, 3) . '/data');
    $notificationsDir = $dataDir . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'inbox' . DIRECTORY_SEPARATOR . 'notifications';
    
    $mentions = [];
    if (is_dir($notificationsDir)) {
        $files = glob($notificationsDir . DIRECTORY_SEPARATOR . $hash . '_*.md');
        if ($files) {
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
                    $yamlParser = new \Indieinabox\Yaml();
                    $frontmatter = $yamlParser->loadString($matches[1]);
                    if (($frontmatter['type'] ?? '') === 'webmention' && ($frontmatter['target_hash'] ?? '') === $hash) {
                        $mentions[] = [
                            'title' => $frontmatter['author_name'] ?? 'External Mention',
                            'source' => $frontmatter['url'] ?? '#',
                            'text' => trim($matches[2]),
                            'whostyle' => $frontmatter['whostyle'] ?? []
                        ];
                    }
                }
            }
        }
    }
    
    if (count($mentions) > 0) {
        echo '<div class="webmentions-section" style="margin-top: 2rem; border-top: 1px solid var(--border); padding-top: 1rem;">';
        echo '<h4>Webmentions (' . count($mentions) . ')</h4>';
        echo '<ul class="webmentions-list" style="list-style: none; padding: 0;">';
        
        foreach ($mentions as $mention) {
            $styleStr = "";
            if (isset($mention['whostyle']['colors']) && is_array($mention['whostyle']['colors'])) {
                $colors = $mention['whostyle']['colors'];
                $styles = [];
                if (isset($colors['dark_bg'])) $styles[] = '--whostyle-bg: ' . htmlspecialchars($colors['dark_bg']);
                if (isset($colors['dark_text'])) $styles[] = '--whostyle-color: ' . htmlspecialchars($colors['dark_text']);
                if (isset($colors['dark_accent'])) $styles[] = '--whostyle-accent: ' . htmlspecialchars($colors['dark_accent']);
                if (!empty($styles)) {
                    $styleStr = implode('; ', $styles) . ';';
                }
            }
            
            $title = htmlspecialchars($mention['title']);
            $source = htmlspecialchars($mention['source']);
            $text = htmlspecialchars($mention['text']);
            
            echo '<li class="webmention-item" style="' . $styleStr . ' background: var(--whostyle-bg, rgba(255,255,255,0.05)); color: var(--whostyle-color, inherit); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">';
            echo '<a href="' . $source . '" target="_blank" rel="noopener noreferrer" style="color: var(--whostyle-accent, var(--accent)); font-weight: bold; display: block; margin-bottom: 0.5rem;">' . $title . '</a>';
            if (!empty($text)) {
                echo '<p style="margin: 0; font-size: 0.9em; opacity: 0.9;">' . $text . '</p>';
            }
            echo '</li>';
        }
        
        echo '</ul>';
        echo '</div>';
    }
}
?>
