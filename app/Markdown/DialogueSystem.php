<?php

declare(strict_types=1);

namespace Indieinabox\Markdown;

/**
 * Handles character dialogues, cowsay speech bubbles, and Comic Chat strips.
 * Resolves tickets #73, #74, and #75.
 */
class DialogueSystem
{
    /**
     * Map of built-in character presets and emotional avatars.
     * @var array<string, array<string, string>>
     */
    private static array $characters = [
        'sh0rk' => [
            'name' => 'sh0rk (Blåhaj)',
            'icon' => '🦈',
            'neutral' => '🦈',
            'happy' => '🦈✨',
            'sorrindo' => '🦈✨',
            'animada' => '🦈🎉',
            'animado' => '🦈🎉',
            'thinking' => '🦈💭',
            'pensativo' => '🦈💭',
            'surprised' => '🦈😲',
            'surpreso' => '🦈😲',
            'angry' => '🦈⚡',
            'bravo' => '🦈⚡',
        ],
        'blahaj' => [
            'name' => 'Blåhaj',
            'icon' => '🦈',
            'neutral' => '🦈',
            'happy' => '🦈✨',
            'sorrindo' => '🦈✨',
            'animada' => '🦈🎉',
            'animado' => '🦈🎉',
            'thinking' => '🦈💭',
            'pensativo' => '🦈💭',
            'surprised' => '🦈😲',
            'surpreso' => '🦈😲',
            'angry' => '🦈⚡',
            'bravo' => '🦈⚡',
        ],
        'mara' => [
            'name' => 'Mara',
            'icon' => '🦊',
            'neutral' => '🦊',
            'happy' => '🦊✨',
            'sorrindo' => '🦊✨',
            'animada' => '🦊🎉',
            'animado' => '🦊🎉',
            'thinking' => '🦊💭',
            'pensativo' => '🦊💭',
            'surprised' => '🦊😲',
            'surpreso' => '🦊😲',
            'angry' => '🦊🔥',
            'bravo' => '🦊🔥',
        ],
    ];

    /**
     * Replaces dialogue, cowsay, and comic markup blocks in raw markdown.
     */
    public static function process(string $text): string
    {
        // 1. Process [comic] ... [/comic]
        $text = (string) preg_replace_callback(
            '/\[comic(?:\s+title="([^"]*)")?\](.*?)\[\/comic\]/si',
            function (array $matches): string {
                $title = trim($matches[1] ?? '');
                $content = trim($matches[2] ?? '');
                return "\n\n" . self::renderComic($content, $title) . "\n\n";
            },
            $text
        );

        // 2. Process [cowsay] ... [/cowsay] or [cowsay char="..."] ... [/cowsay]
        $text = (string) preg_replace_callback(
            '/\[cowsay(?:\s+char="?([^"\]]*)"?)?\](.*?)\[\/cowsay\]/si',
            function (array $matches): string {
                $char = trim($matches[1] ?? 'cow');
                $content = trim($matches[2] ?? '');
                return "\n\n" . self::renderCowsay($content, $char) . "\n\n";
            },
            $text
        );

        // 3. Process [dialog char="..." emote="..."] ... [/dialog]
        $text = (string) preg_replace_callback(
            '/\[dialog(?:\s+char="?([^"\]\s]*)"?)?(?:\s+emote="?([^"\]\s]*)"?)?\](.*?)\[\/dialog\]/si',
            function (array $matches): string {
                $char = trim($matches[1] ?? 'sh0rk');
                $emote = trim($matches[2] ?? 'neutral');
                $content = trim($matches[3] ?? '');
                return "\n\n" . self::renderDialog($char, $emote, $content) . "\n\n";
            },
            $text
        );

        // 4. Process [say:character:emotion] ... [/say]
        $text = (string) preg_replace_callback(
            '/\[say:([a-zA-Z0-9_\-]+)(?::([a-zA-Z0-9_\-]+))?\](.*?)\[\/say\]/si',
            function (array $matches): string {
                $char = trim($matches[1] ?? 'sh0rk');
                $emote = trim($matches[2] ?? 'neutral');
                $content = trim($matches[3] ?? '');
                return "\n\n" . self::renderDialog($char, $emote, $content) . "\n\n";
            },
            $text
        );

        return $text;
    }

    /**
     * Renders a character dialogue box with avatar and emotion (#73).
     */
    public static function renderDialog(string $char, string $emote, string $speech): string
    {
        $charKey = strtolower($char);
        $emoteKey = strtolower($emote ?: 'neutral');

        $preset = self::$characters[$charKey] ?? null;
        $displayName = $preset ? $preset['name'] : htmlspecialchars(ucfirst($char));
        $avatarIcon = $preset[$emoteKey] ?? ($preset['neutral'] ?? '💬');

        if (!$preset && $char !== '') {
            $avatarIcon = '👤';
        }

        $speechEscaped = nl2br(htmlspecialchars($speech));

        $html = '<div class="dialog-box" style="display: flex; gap: 1rem; align-items: flex-start; margin: 1.5rem 0; padding: 1rem; background: rgba(0, 0, 0, 0.03); border-left: 4px solid var(--accent, #007bff); border-radius: 8px;">';
        $html .= '<div class="dialog-avatar" style="text-align: center; min-width: 60px;">';
        $html .= '<div style="font-size: 2.2rem; line-height: 1;" title="' . htmlspecialchars($emoteKey) . '">' . $avatarIcon . '</div>';
        $html .= '<div style="font-size: 0.75rem; font-weight: bold; margin-top: 0.3rem; opacity: 0.85;">' . $displayName . '</div>';
        if ($emoteKey !== 'neutral') {
            $html .= '<div style="font-size: 0.65rem; opacity: 0.65; text-transform: uppercase;">' . htmlspecialchars($emoteKey) . '</div>';
        }
        $html .= '</div>';
        $html .= '<div class="dialog-speech" style="flex: 1; font-size: 0.95rem; line-height: 1.5; padding: 0.5rem 0.8rem; background: var(--bg, #ffffff); border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
        $html .= $speechEscaped;
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Renders cowsay speech bubble (#74).
     */
    public static function renderCowsay(string $text, string $char = 'cow'): string
    {
        $char = strtolower($char);
        $lines = explode("\n", wordwrap($text, 40));
        $maxLen = 0;
        foreach ($lines as $l) {
            $len = mb_strlen(trim($l));
            if ($len > $maxLen) {
                $maxLen = $len;
            }
        }
        $maxLen = max($maxLen, 10);

        $bubble = ' ' . str_repeat('_', $maxLen + 2) . "\n";
        if (count($lines) === 1) {
            $bubble .= '< ' . str_pad(trim($lines[0]), $maxLen) . " >\n";
        } else {
            foreach ($lines as $idx => $line) {
                $t = trim($line);
                if ($idx === 0) {
                    $bubble .= '/ ' . str_pad($t, $maxLen) . " \\\n";
                } elseif ($idx === count($lines) - 1) {
                    $bubble .= '\\ ' . str_pad($t, $maxLen) . " /\n";
                } else {
                    $bubble .= '| ' . str_pad($t, $maxLen) . " |\n";
                }
            }
        }
        $bubble .= ' ' . str_repeat('-', $maxLen + 2) . "\n";

        if ($char === 'blahaj' || $char === 'sh0rk') {
            $bubble .= "        \\ \n";
            $bubble .= "         \\   ><(((°>  [🦈 sh0rk]\n";
        } elseif ($char === 'tux') {
            $bubble .= "   \\   \n";
            $bubble .= "    \\   .--.  \n";
            $bubble .= "       |o_o | \n";
            $bubble .= "       |:_/ | \n";
            $bubble .= "      //   \\ \\\n";
            $bubble .= "     (|     | )\n";
            $bubble .= "    /'\\_   _/`\\\n";
            $bubble .= "    \\___)=(___/\n";
        } else {
            $bubble .= "        \\   ^__^\n";
            $bubble .= "         \\  (oo)\\_______\n";
            $bubble .= "            (__)\\       )\\/\\\n";
            $bubble .= "                ||----w |\n";
            $bubble .= "                ||     ||\n";
        }

        $escaped = htmlspecialchars($bubble);

        return '<div class="cowsay-container" style="margin: 1.5rem 0; overflow-x: auto;"><pre class="cowsay-bubble" style="background: rgba(0,0,0,0.05); padding: 1rem; border-radius: 6px; font-family: monospace; font-size: 0.85em; display: inline-block;"><code>' . $escaped . '</code></pre></div>';
    }

    /**
     * Renders Comic Chat style multi-panel strip (#75).
     */
    public static function renderComic(string $markup, string $title = ''): string
    {
        $rawLines = explode("\n", $markup);
        $panels = [];

        foreach ($rawLines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Pattern: Speaker (emotion): Speech OR Speaker: Speech
            if (preg_match('/^([^:\(]+)(?:\(([^)]+)\))?\s*:\s*(.+)$/u', $line, $m)) {
                $speaker = trim($m[1]);
                $emotion = isset($m[2]) ? trim($m[2]) : 'neutro';
                $speech = trim($m[3]);
                $isThought = str_starts_with($emotion, 'pens') || str_starts_with($speech, 'oO');

                $panels[] = [
                    'speaker' => $speaker,
                    'emotion' => $emotion,
                    'speech' => $speech,
                    'thought' => $isThought,
                ];
            }
        }

        if (empty($panels)) {
            return '';
        }

        $html = '<div class="comic-chat-strip" style="margin: 2rem 0; padding: 1rem; background: var(--bg, #fdf6e3); border: 2px solid var(--fg, #000); border-radius: 8px; box-shadow: 4px 4px 0 rgba(0,0,0,0.15);">';
        if ($title !== '') {
            $html .= '<div class="comic-title" style="font-weight: bold; font-size: 1.1em; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid var(--fg, #000); padding-bottom: 0.5rem; margin-bottom: 1rem; text-align: center;">' . htmlspecialchars($title) . '</div>';
        }

        $html .= '<div class="comic-panels-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">';

        foreach ($panels as $idx => $p) {
            $speakerLower = strtolower($p['speaker']);
            $emoteLower = strtolower($p['emotion']);
            $avatar = self::$characters[$speakerLower][$emoteLower] ?? (self::$characters[$speakerLower]['neutral'] ?? '👤');

            $bubbleStyle = $p['thought']
                ? 'border: 2px dashed var(--fg, #000); border-radius: 16px;'
                : 'border: 2px solid var(--fg, #000); border-radius: 12px;';

            $html .= '<div class="comic-panel" style="border: 2px solid var(--fg, #000); background: #ffffff; padding: 0.8rem; border-radius: 6px; display: flex; flex-direction: column; justify-content: space-between; min-height: 180px; position: relative;">';
            $html .= '<div class="panel-speech-bubble" style="' . $bubbleStyle . ' padding: 0.6rem 0.8rem; font-size: 0.9em; background: #ffffff; margin-bottom: 1rem; position: relative;">';
            $html .= htmlspecialchars($p['speech']);
            $html .= '</div>';

            $html .= '<div class="panel-character" style="display: flex; align-items: center; gap: 0.5rem; margin-top: auto;">';
            $html .= '<div style="font-size: 2rem; line-height: 1;">' . $avatar . '</div>';
            $html .= '<div>';
            $html .= '<div style="font-weight: bold; font-size: 0.85em;">' . htmlspecialchars($p['speaker']) . '</div>';
            $html .= '<div style="font-size: 0.7em; opacity: 0.7; font-style: italic;">' . htmlspecialchars($p['emotion']) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
