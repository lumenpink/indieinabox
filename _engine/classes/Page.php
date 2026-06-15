<?php

declare(strict_types=1);

namespace Indieinabox;

use DateTime;
use Indieinabox\Page\Metadata;
use Indieinabox\Page\Content;
use Indieinabox\Page\Localization;

/**
 * Class Page
 *
 * This class represents a page and composes metadata, content, and localization.
 */
class Page implements \ArrayAccess
{
    /**
     * @var Metadata
     */
    public $metadata;

    /**
     * @var Content
     */
    public $content;

    /**
     * @var Localization
     */
    public $localization;

    /**
     * @var DateTime
     */
    public $date;

    /**
     * @var string
     */
    public $relpath;

    /**
     * @var string
     */
    public $slug;

    /**
     * Page constructor.
     *
     * @param Metadata $metadata
     * @param Content $content
     * @param Localization $localization
     * @param DateTime|null $date
     * @param string $relpath
     * @param string $slug
     */
    public function __construct(
        ?Metadata $metadata,
        ?Content $content,
        ?Localization $localization,
        ?DateTime $date = null,
        string $relpath = "",
        string $slug = "untitled"
    ) {
        $this->metadata = $metadata ?? new Metadata();
        $this->content = $content ?? new Content();
        $this->localization = $localization ?? new Localization();
        $this->date = $date ?? new DateTime('now');
        $this->relpath = $relpath;
        $this->slug = $slug;
    }

    /**
     * Create a Page object from a raw array structure.
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $metadata = new Metadata(
            (array) ($data['category'] ?? ['No Category']),
            (array) ($data['tags'] ?? ['No Tag']),
            (string) ($data['title'] ?? 'Untitled'),
            (string) ($data['nick'] ?? 'untitled'),
            (bool) ($data['noauthor'] ?? false),
            (string) ($data['kind'] ?? 'note'),
            (string) ($data['layout'] ?? 'page')
        );

        $content = new Content(
            (string) ($data['content'] ?? 'Hello World'),
            (string) ($data['originalcontent'] ?? 'Hello World'),
            (array) ($data['images'] ?? [])
        );

        $localization = new Localization(
            (string) ($data['lang'] ?? 'en'),
            (string) ($data['langpath'] ?? ''),
            $data['langslug'] ?? 'untitled',
            (array) ($data['otherlang'] ?? []),
            (array) ($data['otherlangpath'] ?? []),
            (string) ($data['localizeddate'] ?? 'Saturday, January 1 of 2001, 00:00 UTC'),
            (string) ($data['localizedkind'] ?? 'note')
        );

        $date = null;
        if (isset($data['date'])) {
            if ($data['date'] instanceof DateTime) {
                $date = $data['date'];
            } else {
                $epoch = $data['date'];
                if (is_float($epoch)) {
                    $epoch = intval($epoch);
                }
                if (is_int($epoch) || (is_string($epoch) && is_numeric($epoch))) {
                    $date = DateTime::createFromFormat("U", strval($epoch));
                } else {
                    $date = new DateTime($epoch);
                }
            }
        }

        return new self(
            $metadata,
            $content,
            $localization,
            $date,
            (string) ($data['relpath'] ?? ''),
            (string) ($data['slug'] ?? 'untitled')
        );
    }

    // --- ArrayAccess Implementation ---

    public function offsetExists($offset): bool
    {
        return in_array($offset, [
            'lang', 'langpath', 'langslug', 'otherlang', 'otherlangpath',
            'localizeddate', 'localizedkind', 'title', 'tags', 'category',
            'nick', 'noauthor', 'kind', 'layout', 'content', 'originalcontent',
            'images', 'date', 'relpath', 'slug'
        ]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        switch ($offset) {
            case 'lang':
                return $this->localization->lang;
            case 'langpath':
                return $this->localization->langpath;
            case 'langslug':
                return $this->localization->langslug;
            case 'otherlang':
                return $this->localization->otherlang;
            case 'otherlangpath':
                return $this->localization->otherlangpath;
            case 'localizeddate':
                return $this->localization->localizeddate;
            case 'localizedkind':
                return $this->localization->localizedkind;
            case 'title':
                return $this->metadata->title;
            case 'tags':
                return $this->metadata->tags;
            case 'category':
                return $this->metadata->category;
            case 'nick':
                return $this->metadata->nick;
            case 'noauthor':
                return $this->metadata->noauthor;
            case 'kind':
                return $this->metadata->kind;
            case 'layout':
                return $this->metadata->layout;
            case 'content':
                return $this->content->content;
            case 'originalcontent':
                return $this->content->originalcontent;
            case 'images':
                return $this->content->images;
            case 'date':
                return $this->date instanceof DateTime ? $this->date->getTimestamp() : $this->date;
            case 'relpath':
                return $this->relpath;
            case 'slug':
                return $this->slug;
        }
        return null;
    }

    public function offsetSet($offset, $value): void
    {
        switch ($offset) {
            case 'lang':
                $this->localization->lang = $value;
                break;
            case 'langpath':
                $this->localization->langpath = $value;
                break;
            case 'langslug':
                $this->localization->langslug = $value;
                break;
            case 'otherlang':
                $this->localization->otherlang = $value;
                break;
            case 'otherlangpath':
                $this->localization->otherlangpath = $value;
                break;
            case 'localizeddate':
                $this->localization->localizeddate = $value;
                break;
            case 'localizedkind':
                $this->localization->localizedkind = $value;
                break;
            case 'title':
                $this->metadata->title = $value;
                break;
            case 'tags':
                $this->metadata->tags = $value;
                break;
            case 'category':
                $this->metadata->category = $value;
                break;
            case 'nick':
                $this->metadata->nick = $value;
                break;
            case 'noauthor':
                $this->metadata->noauthor = (bool) $value;
                break;
            case 'kind':
                $this->metadata->kind = $value;
                break;
            case 'layout':
                $this->metadata->layout = $value;
                break;
            case 'content':
                $this->content->content = $value;
                break;
            case 'originalcontent':
                $this->content->originalcontent = $value;
                break;
            case 'images':
                $this->content->images = $value;
                break;
            case 'date':
                if ($value instanceof DateTime) {
                    $this->date = $value;
                } else {
                    $this->date = new DateTime(is_numeric($value) ? "@{$value}" : $value);
                }
                break;
            case 'relpath':
                $this->relpath = $value;
                break;
            case 'slug':
                $this->slug = $value;
                break;
        }
    }

    public function offsetUnset($offset): void
    {
        // No-op for safety
    }
}
