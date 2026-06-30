<?php

namespace Indieinabox;

class Whostyles {
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    private static $ALPHABET_MAP = null;

    private static function getAlphabetMap(): array {
        if (self::$ALPHABET_MAP === null) {
            self::$ALPHABET_MAP = array_flip(str_split(self::ALPHABET));
        }
        return self::$ALPHABET_MAP;
    }
    
    private const RADIXES = [
        'typography' => 28,
        'transform' => 4,
        'align' => 4,
        'list' => 5,
        'border_style' => 10,
        'bg_texture' => 32,
        'border_width' => 7,
        'border_radius' => 17,
        'shadow_offset' => 9,
        'shadow_blur' => 9,
        'letter_spacing' => 26
    ];

    public static function decodeBase64(string $str): int {
        $map = self::getAlphabetMap();
        $value = 0;
        $multiplier = 1;
        for ($i = 0; $i < strlen($str); $i++) {
            if (!isset($map[$str[$i]])) throw new \Exception("Invalid base64 character");
            $charVal = $map[$str[$i]];
            $value += $charVal * $multiplier;
            $multiplier *= 64;
        }
        return $value;
    }

    public static function encodeBase64(int $value, int $length): string {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= self::ALPHABET[$value % 64];
            $value = intdiv($value, 64);
        }
        return $result;
    }

    public static function decodeColor(string $str): string {
        $map = self::getAlphabetMap();
        $val = 0;
        $val = ($val << 6) | $map[$str[0]];
        $val = ($val << 6) | $map[$str[1]];
        $val = ($val << 6) | $map[$str[2]];
        $val = ($val << 6) | $map[$str[3]];
        return '#' . str_pad(dechex($val), 6, '0', STR_PAD_LEFT);
    }

    public static function encodeColor(string $hex): string {
        $val = hexdec(ltrim($hex, '#'));
        $result = '';
        $result .= self::ALPHABET[($val >> 18) & 63];
        $result .= self::ALPHABET[($val >> 12) & 63];
        $result .= self::ALPHABET[($val >> 6) & 63];
        $result .= self::ALPHABET[$val & 63];
        return $result;
    }

    public static function decode(string $hash): ?array {
        if (PHP_INT_MAX < 179639500800) {
            throw new \Exception("Whostyle v2 requires a 64-bit PHP environment or GMP/bcmath extensions for safe decoding.");
        }

        if (!preg_match('/^{ws2:([A-Za-z0-9\-_]{7})([A-Za-z0-9\-_]{32})}$/', $hash, $matches)) {
            return null;
        }

        $configB64 = $matches[1];
        $colorsB64 = $matches[2];

        try {
            $value = self::decodeBase64($configB64);
        } catch (\Exception $e) {
            return null;
        }

        $config = [];
        foreach (self::RADIXES as $key => $radix) {
            $config[$key] = $value % $radix;
            $value = intdiv($value, $radix);
        }

        $colors = [];
        $colorOrder = [
            'light_bg', 'light_text', 'light_accent', 'light_texture',
            'dark_bg', 'dark_text', 'dark_accent', 'dark_texture'
        ];
        
        for ($i = 0; $i < count($colorOrder); $i++) {
            $colors[$colorOrder[$i]] = self::decodeColor(substr($colorsB64, $i * 4, 4));
        }

        return ['config' => $config, 'colors' => $colors];
    }

    public static function encode(array $config, array $colors): string {
        if (PHP_INT_MAX < 179639500800) {
            throw new \Exception("Whostyle v2 requires a 64-bit PHP environment or GMP/bcmath extensions for safe encoding.");
        }

        $value = 0;
        
        $order = array_reverse(array_keys(self::RADIXES));
        foreach ($order as $key) {
            $value = $value * self::RADIXES[$key] + ($config[$key] ?? 0);
        }
        
        $configB64 = self::encodeBase64($value, 7);
        
        $colorOrder = [
            'light_bg', 'light_text', 'light_accent', 'light_texture',
            'dark_bg', 'dark_text', 'dark_accent', 'dark_texture'
        ];
        
        $colorsB64 = '';
        foreach ($colorOrder as $key) {
            $colorsB64 .= self::encodeColor($colors[$key] ?? '#000000');
        }
        
        return "{ws2:{$configB64}{$colorsB64}}";
    }

    public static function extract(string $html): ?string {
        $metaHash = null;
        $inlineHash = null;

        if (preg_match_all('/((?:<|&lt;)meta(?:(?!(?:>|&gt;)).){0,250}?name=(?:["\']|&quot;|&#39;|&#039;)?whostyle(?:["\']|&quot;|&#39;|&#039;)?(?:(?!(?:>|&gt;)).){0,250}?content=(?:["\']|&quot;|&#39;|&#039;)?{ws2:[A-Za-z0-9\-_]{39}}(?:["\']|&quot;|&#39;|&#039;)?(?:(?!(?:>|&gt;)).){0,250}?(?:>|&gt;))|({ws2:[A-Za-z0-9\-_]{39}})/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!empty($match[1])) {
                    if (!$metaHash && preg_match('/{ws2:[A-Za-z0-9\-_]{39}}/', $match[1], $m)) {
                        $metaHash = $m[0];
                        break;
                    }
                } elseif (!empty($match[2])) {
                    if (!$inlineHash) {
                        $inlineHash = $match[2];
                    }
                }
            }
        }

        return $metaHash ?: $inlineHash;
    }

    public static function clean(string $html): string {
        return preg_replace_callback(
            '/((?:<|&lt;)meta(?:(?!(?:>|&gt;)).){0,250}?name=(?:["\']|&quot;|&#39;|&#039;)?whostyle(?:["\']|&quot;|&#39;|&#039;)?(?:(?!(?:>|&gt;)).){0,250}?content=(?:["\']|&quot;|&#39;|&#039;)?{ws2:[A-Za-z0-9\-_]{39}}(?:["\']|&quot;|&#39;|&#039;)?(?:(?!(?:>|&gt;)).){0,250}?(?:>|&gt;))|({ws2:[A-Za-z0-9\-_]{39}})/is',
            function ($matches) {
                if (!empty($matches[1])) {
                    return $matches[1];
                }
                return '';
            },
            $html
        );
    }

    private const MAPPINGS = [
        'transforms' => ['none', 'capitalize', 'uppercase', 'lowercase'],
        'aligns' => ['left', 'right', 'center', 'justify'],
        'lists' => ['disc', 'circle', 'square', 'decimal', 'lower-roman'],
        'borders' => ['none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset'],
        'typography' => [
            'system-ui', 'segoe-roboto', 'helvetica-neue', 'verdana', 'trebuchet',
            'tahoma', 'century-gothic', 'franklin-gothic', 'gill-sans', 'arial-rounded',
            'georgia', 'times-new-roman', 'garamond', 'palatino', 'baskerville',
            'bookman', 'cambria', 'didot', 'bodoni', 'rockwell',
            'monospace', 'consolas', 'courier-new', 'monaco', 'lucida-console',
            'andale-mono', 'sf-mono', 'cascadia-code'
        ],
        'textures' => [
            'none', 'noise', 'stripes-v', 'stripes-h', 'stripes-d-right',
            'stripes-d-left', 'pinstripes', 'wavy-lines', 'zigzag-lines', 'grid-standard',
            'grid-fine', 'grid-isometric', 'crosses', 'crosshatch', 'checkerboard',
            'checkerboard-tilt', 'triangles', 'diamonds', 'argyle', 'honeycomb',
            'chevron', 'houndstooth', 'brick-wall', 'dots-sparse', 'polka-dots',
            'circles-concentric', 'scallop', 'waves', 'woven', 'denim',
            'tartan', 'confetti'
        ]
    ];

    private static function getLuminance(string $hex): float {
        $r = hexdec(substr($hex, 1, 2)) / 255.0;
        $g = hexdec(substr($hex, 3, 2)) / 255.0;
        $b = hexdec(substr($hex, 5, 2)) / 255.0;

        $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function getContrast(string $hex1, string $hex2): float {
        $l1 = self::getLuminance($hex1);
        $l2 = self::getLuminance($hex2);
        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    public static function generateAttributes(string $hash): string {
        $decoded = self::decode($hash);
        if (!$decoded) return '';

        $config = $decoded['config'];
        $colors = $decoded['colors'];
        $M = self::MAPPINGS;

        $typographyClass = $M['typography'][$config['typography']] ?? 'system-ui';
        $textureClass = $M['textures'][$config['bg_texture']] ?? 'none';

        $classNames = [
            'whostyle-container',
            "ws-typography-{$typographyClass}",
            "ws-texture-{$textureClass}"
        ];

        $transform = $M['transforms'][$config['transform']] ?? 'none';
        $align = $M['aligns'][$config['align']] ?? 'left';
        $list = $M['lists'][$config['list']] ?? 'disc';
        $bstyle = $M['borders'][$config['border_style']] ?? 'none';
        
        $bwidth = $config['border_width'] . 'px';
        $bradius = $config['border_radius'] . 'px';
        $soffset = ($config['shadow_offset'] - 4) . 'px';
        $sblur = $config['shadow_blur'] . 'px';
        $lspacing = number_format($config['letter_spacing'] * 0.1 - 0.5, 1, '.', '') . 'px';

        $l_bg = $colors['light_bg'];
        $l_text = $colors['light_text'];
        $l_accent = $colors['light_accent'];
        $l_texture = $colors['light_texture'];

        if (self::getContrast($l_bg, $l_text) < 4.5 || self::getContrast($l_bg, $l_accent) < 4.5) {
            $l_bg = '#ffffff';
            $l_text = '#000000';
            $l_accent = '#0000ff';
            $l_texture = 'transparent';
        }

        $d_bg = $colors['dark_bg'];
        $d_text = $colors['dark_text'];
        $d_accent = $colors['dark_accent'];
        $d_texture = $colors['dark_texture'];

        if (self::getContrast($d_bg, $d_text) < 4.5 || self::getContrast($d_bg, $d_accent) < 4.5) {
            $d_bg = '#000000';
            $d_text = '#ffffff';
            $d_accent = '#00aaff';
            $d_texture = 'transparent';
        }

        $styles = [
            "--ws-transform: {$transform}",
            "--ws-align: {$align}",
            "--ws-list: {$list}",
            "--ws-bstyle: {$bstyle}",
            "--ws-bwidth: {$bwidth}",
            "--ws-bradius: {$bradius}",
            "--ws-soffset: {$soffset}",
            "--ws-sblur: {$sblur}",
            "--ws-lspacing: {$lspacing}",
            "--ws-light-bg: {$l_bg}",
            "--ws-light-text: {$l_text}",
            "--ws-light-accent: {$l_accent}",
            "--ws-light-texture: {$l_texture}",
            "--ws-dark-bg: {$d_bg}",
            "--ws-dark-text: {$d_text}",
            "--ws-dark-accent: {$d_accent}",
            "--ws-dark-texture: {$d_texture}"
        ];

        $classes = implode(' ', $classNames);
        $styleStr = implode('; ', $styles) . ';';

        return "class=\"{$classes}\" style=\"{$styleStr}\"";
    }
}
