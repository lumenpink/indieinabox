<?php

declare(strict_types=1);

namespace Indieinabox\Translations;

/**
 * Class UrlTranslations
 */
class UrlTranslations
{
    /**
     * @var array<string, array<string, string>>
     */
    private $translations;

    /**
     * UrlTranslations constructor.
     *
     * @param array<string, array<string, string>> $translations
     */
    public function __construct(array $translations)
    {
        $this->translations = $translations;
    }

    /**
     * Get the translated slug for a given original content and language.
     *
     * @param string $originalContent
     * @param string $lang
     * @return string
     */
    public function getTranslatedSlug(string $originalContent, string $lang): string
    {
        return $this->translations[$originalContent][$lang] ?? $originalContent;
    }

    /**
     * Get the original content for a given nick and language.
     *
     * @param string $nick
     * @param string $defaultLang
     * @return string
     */
    public function getOriginalContent(string $nick, string $defaultLang): string
    {
        return $this->translations[$nick][$defaultLang] ?? $nick;
    }
}
