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
 */
class Localization
{
    /** @var array<string> */
    private array $lang;
    private string $defaultLang;

    /**
     * Localization constructor.
     *
     * @param array<string>|string|number|null $lang
     * @param string $defaultLang
     */
    public function __construct(
        $lang = null,
        string $defaultLang = "en"
    ) {
        $lang = $this->createArrayFromValue($lang);
        if (empty($lang)) {
            $lang = [$defaultLang];
        }
        $this->lang = $lang;
        $this->defaultLang = $defaultLang;
    }

    /**
     * @param string $name
     * @return array<string>|string|number|null
     */
    public function __get(string $name)
    {
        return $this->$name;
    }
    /**
     * @param string $name
     * @param array<string>|string|number|null $value
     */
    public function __set(string $name, $value)
    {
        switch ($name) {
            case 'lang':
                $this->lang = $this->createArrayFromValue($value);
                return;
            case 'defaultLang':
                $this->defaultLang = $value;
                return;
            default:
                throw new \Exception("Property {$name} does not exist");
        }
    }
    /**
     * Creates an array from a value.
     *
     * @param array<string>|string|number|null $value
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
