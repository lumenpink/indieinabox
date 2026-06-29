<?php

declare(strict_types=1);

namespace Indieinabox;

class Whostyles
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    
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

    public static function decodeBase64(string $str): int
    {
        $value = 0;
        $multiplier = 1;
        for ($i = 0; $i < strlen($str); $i++) {
            $charVal = strpos(self::ALPHABET, $str[$i]);
            if ($charVal === false) {
                throw new \Exception("Invalid base64 character");
            }
            $value += $charVal * $multiplier;
            $multiplier *= 64;
        }
        return $value;
    }

    public static function encodeBase64(int $value, int $length): string
    {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= self::ALPHABET[$value % 64];
            $value = intdiv($value, 64);
        }
        return $result;
    }

    public static function decodeColor(string $str): string
    {
        $val = 0;
        $val = ($val << 6) | strpos(self::ALPHABET, $str[0]);
        $val = ($val << 6) | strpos(self::ALPHABET, $str[1]);
        $val = ($val << 6) | strpos(self::ALPHABET, $str[2]);
        $val = ($val << 6) | strpos(self::ALPHABET, $str[3]);
        return '#' . str_pad(dechex($val), 6, '0', STR_PAD_LEFT);
    }

    public static function encodeColor(string $hex): string
    {
        $val = hexdec(ltrim($hex, '#'));
        $result = '';
        $result .= self::ALPHABET[($val >> 18) & 63];
        $result .= self::ALPHABET[($val >> 12) & 63];
        $result .= self::ALPHABET[($val >> 6) & 63];
        $result .= self::ALPHABET[$val & 63];
        return $result;
    }

    public static function getLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255.0;
        $g = hexdec(substr($hex, 2, 2)) / 255.0;
        $b = hexdec(substr($hex, 4, 2)) / 255.0;

        $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    public static function getContrastRatio(string $hex1, string $hex2): float
    {
        $l1 = self::getLuminance($hex1);
        $l2 = self::getLuminance($hex2);

        $lightest = max($l1, $l2);
        $darkest = min($l1, $l2);

        return ($lightest + 0.05) / ($darkest + 0.05);
    }

    /**
     * @param string $hash
     * @return array{config: array<string, int>, colors: array<string, string>}|null
     */
    public static function decode(string $hash): ?array
    {
        if (PHP_INT_MAX < 179639500800) {
            throw new \Exception(
                "Whostyle v2 requires a 64-bit PHP environment or GMP/bcmath extensions for safe decoding."
            );
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

        if ($value >= 1796395008000) {
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

        if (self::getContrastRatio($colors['light_bg'], $colors['light_text']) < 4.5) {
            return null;
        }
        if (self::getContrastRatio($colors['light_bg'], $colors['light_accent']) < 4.5) {
            return null;
        }
        if (self::getContrastRatio($colors['dark_bg'], $colors['dark_text']) < 4.5) {
            return null;
        }
        if (self::getContrastRatio($colors['dark_bg'], $colors['dark_accent']) < 4.5) {
            return null;
        }

        return ['config' => $config, 'colors' => $colors];
    }

    /**
     * @param array<string, int> $config
     * @param array<string, string> $colors
     * @return string
     */
    public static function encode(array $config, array $colors): string
    {
        if (PHP_INT_MAX < 179639500800) {
            throw new \Exception(
                "Whostyle v2 requires a 64-bit PHP environment or GMP/bcmath extensions for safe encoding."
            );
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

    public static function discoverInline(string $html): ?string
    {
        $metaHashes = [];
        $regex = '/<meta\s+(?:[^>]*\s+)?name=["\']?whostyle["\']?\s+'
            . '(?:[^>]*\s+)?content=["\']?({ws2:[A-Za-z0-9\-_]{39}})["\']?[^>]*>/i';
        if (preg_match_all($regex, $html, $matches)) {
            $metaHashes = $matches[1];
        }
        
        $htmlWithoutMeta = preg_replace('/<meta[^>]*>/i', '', $html);
        
        $inlineHashes = [];
        if (preg_match_all('/({ws2:[A-Za-z0-9\-_]{39}})/', $htmlWithoutMeta, $matches)) {
            $inlineHashes = $matches[1];
        }
        
        foreach ($inlineHashes as $hash) {
            if (self::decode($hash) !== null) {
                return $hash;
            }
        }
        
        foreach ($metaHashes as $hash) {
            if (self::decode($hash) !== null) {
                return $hash;
            }
        }
        
        return null;
    }
}
