<?php

declare(strict_types=1);

namespace Indieinabox\Site;

/**
 * Class Localization
 *
 * Holds language and localization-related configurations.
 *
 * @property array<string> $lang
 * @property string $defaultLang
 * @property string $langDisplayMode
 */
class Localization
{
    /** @var array<string> */
    private array $lang;
    /**
     * @var string
     */
    private string $defaultLang;
    /**
     * @var string
     */
    private string $langDisplayMode;

    /**
     * Localization constructor.
     *
     * @param array<string>|string|int|float|null $lang
     * @param string $defaultLang
     * @param string $langDisplayMode
     */
    public function __construct(
        $lang = null,
        string $defaultLang = "en",
        string $langDisplayMode = "native"
    ) {
        $lang = $this->createArrayFromValue($lang);
        if (empty($lang)) {
            $lang = [$defaultLang];
        }
        $this->lang = $lang;
        $this->defaultLang = $defaultLang;
        $this->langDisplayMode = in_array($langDisplayMode, ['short', 'native'], true) ? $langDisplayMode : 'native';
    }

    /**
     * @param string $name
     * @return array<string>|string|int|float|null
     */
    public function __get(string $name)
    {
        $lower = strtolower($name);
        if ($lower === 'defaultlang') {
            return $this->defaultLang;
        }
        if ($lower === 'langdisplaymode' || $lower === 'lang_display_mode') {
            return $this->langDisplayMode;
        }
        return $this->$name;
    }
    /**
     * @param string $name
     * @param array<string>|string|int|float|null $value
     */
    public function __set(string $name, $value)
    {
        switch (strtolower($name)) {
            case 'lang':
                $this->lang = $this->createArrayFromValue($value);
                return;
            case 'defaultlang':
                $this->defaultLang = (string) $value;
                return;
            case 'langdisplaymode':
            case 'lang_display_mode':
                $this->langDisplayMode = in_array($value, ['short', 'native'], true) ? (string) $value : 'native';
                return;
            default:
                throw new \Exception("Property {$name} does not exist");
        }
    }
    /**
     * Creates an array from a value.
     *
     * @param array<string>|string|int|float|null $value
     * @return array<string>
     */
    public function createArrayFromValue($value): array
    {
        if (is_string($value) || is_numeric($value)) {
            return [strval($value)];
        }
        if (is_array($value)) {
            return $value;
        }
        return [];
    }
}
