<?php

declare(strict_types=1);

namespace Indieinabox\Translations;

class TranslatedUrl
{
    public string $slug;
    public string $nick;

    public function __construct(string $slug, string $nick)
    {
        $this->slug = $slug;
        $this->nick = $nick;
    }
}


class TranslatedUrlCollection
{
    /**
     * @var array<string, TranslatedUrl>
     */
    public array $urls;

    /**
     * @param array<string, TranslatedUrl> $urls
     */
    public function __construct(array $urls)
    {
        $this->urls = $urls;
    }

    public function getTranslatedUrl(string $nick, string $defaultLang): TranslatedUrl
    {
        return $this->urls[$nick][$defaultLang] ?? $nick;
    }

    public function getTranslatedUrlCollection(string $defaultLang): TranslatedUrlCollection
    {
        return new TranslatedUrlCollection(array_filter($this->urls, function (TranslatedUrl $url) use ($defaultLang) {
            return $url->nick === $defaultLang;
        }));
    }
}


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
