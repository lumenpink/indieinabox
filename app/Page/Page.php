<?php

declare(strict_types=1);

namespace Indieinabox\Page;

use DateTime;
use Indieinabox\Localization\Translator;

/**
 * Class Page
 *
 * This class represents a page and composes metadata, content, and localization.
 *
 * @property string $lang
 * @property string $langpath
 * @property array<string>|string $langslug
 * @property array<string> $otherlang
 * @property array<string> $otherlangpath
 * @property string $localizeddate
 * @property string $localizedkind
 * @property string $title
 * @property array<string> $tags
 * @property array<string> $category
 * @property string $nick
 * @property bool $noauthor
 * @property string $kind
 * @property string $layout
 * @property string $originalcontent
 * @property array<string> $images
 * @property string $rawBody
 * @property string $isodate
 * @property array<string, mixed> $frontmatter Raw frontmatter data array via __get
 * @property \DateTimeInterface|null $published Publication date via __get
 */
class Page
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
     * @var string|null
     */
    public ?string $shortlink = null;

    /**
     * @var string|null
     */
    public ?string $filepath = null;

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
        ?Metadata $metadata = null,
        ?Content $content = null,
        ?Localization $localization = null,
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
     * Magic getter to expose shortcut properties.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        switch ($name) {
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
                if (isset($this->metadata->title)) {
                    return $this->metadata->title;
                }
                $kind = $this->kind ?? 'note';
                $indiewebActionKinds = ['reply', 'like', 'repost', 'bookmark', 'rsvp', 'listen', 'watch', 'read'];
                if (in_array($kind, $indiewebActionKinds)) {
                    return Translator::translate($kind . '_a_post');
                }
                return Translator::translate('Untitled');
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
            case 'rawBody':
                return $this->content->rawBody;
            case 'isodate':
                return $this->date !== null ? $this->date->format('c') : '';
        }
        return null;
    }

    /**
     * Magic setter to modify shortcut properties.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set(string $name, $value): void
    {
        switch ($name) {
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
            case 'rawBody':
                $this->content->rawBody = $value;
                break;
        }
    }

    /**
     * Magic isset check for shortcut properties.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return in_array($name, [
            'lang', 'langpath', 'langslug', 'otherlang', 'otherlangpath',
            'localizeddate', 'localizedkind', 'title', 'tags', 'category',
            'nick', 'noauthor', 'kind', 'layout', 'content', 'originalcontent',
            'images', 'rawBody', 'isodate'
        ]);
    }

    /**
     * Create a Page object from a raw array structure.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        if (isset($data['kind']) && in_array($data['kind'], ['garden', 'jardim'], true)) {
            $lang = $data['lang'] ?? null;
            $data['flowerbed'] = isset($data['flowerbed']) ? (array) $data['flowerbed'] : [Translator::translate('general', $lang)];
            $data['confidence'] = $data['confidence'] ?? 'possible';
            $data['maturity'] = $data['maturity'] ?? 'sprout';
            $data['importance'] = $data['importance'] ?? 'trivial';
        }
        $metadata = new Metadata(
            (array) ($data['category'] ?? ['No Category']),
            (array) ($data['tags'] ?? ['No Tag']),
            (string) ($data['title'] ?? 'Untitled'),
            (string) ($data['nick'] ?? 'untitled'),
            (bool) ($data['noauthor'] ?? false),
            (string) ($data['kind'] ?? 'note'),
            (string) ($data['layout'] ?? 'page'),
            isset($data['maturity']) ? (string)$data['maturity'] : null,
            isset($data['menu']) ? $data['menu'] : null,
            isset($data['menu_order']) ? (int)$data['menu_order'] : null,
            isset($data['hide_title']) ? (bool)$data['hide_title'] : false,
            isset($data['hide_on_rss']) ? $data['hide_on_rss'] : false
        );

        if (isset($data['flowerbed'])) {
            $metadata->flowerbed = (array) $data['flowerbed'];
        }
        if (isset($data['confidence'])) {
            $metadata->confidence = (string) $data['confidence'];
        }
        if (isset($data['importance'])) {
            $metadata->importance = (string) $data['importance'];
        }

        // Map any unhandled properties into metadata
        $handledKeys = [
            'category', 'tags', 'title', 'nick', 'noauthor', 'kind', 'layout', 'maturity',
            'menu', 'menu_order', 'hide_title', 'hide_on_rss', 'flowerbed', 'confidence', 'importance',
            'content', 'originalcontent', 'images', 'rawBody', 'lang', 'langpath', 'langslug',
            'otherlang', 'otherlangpath', 'localizeddate', 'localizedkind', 'date', 'slug', 'relpath'
        ];
        
        foreach ($data as $key => $value) {
            if (!in_array($key, $handledKeys)) {
                $metadata->{$key} = $value;
            }
        }

        $content = new Content(
            (string) ($data['content'] ?? 'Hello World'),
            (string) ($data['originalcontent'] ?? 'Hello World'),
            (array) ($data['images'] ?? []),
            (string) ($data['rawBody'] ?? '')
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

        $slug = (string) ($data['slug'] ?? 'untitled');
        $relpath = (string) ($data['relpath'] ?? '');
        if ($relpath === '') {
            $cleanSlug = ltrim($slug, '/');
            if ($cleanSlug === '' || $cleanSlug === 'index.html') {
                $relpath = './';
            } else {
                $slashCount = substr_count($cleanSlug, '/');
                $relpath = $slashCount > 0 ? str_repeat('../', $slashCount) : './';
            }
        }

        return new self(
            $metadata,
            $content,
            $localization,
            $date,
            $relpath,
            $slug
        );
    }

    /**
     * Deep clones the Page object to ensure nested Metadata, Content, 
     * and Localization objects are also duplicated.
     */
    public function __clone()
    {
        if ($this->metadata !== null) {
            $this->metadata = clone $this->metadata;
        }
        if ($this->content !== null) {
            $this->content = clone $this->content;
        }
        if ($this->localization !== null) {
            $this->localization = clone $this->localization;
        }
        if ($this->date !== null) {
            $this->date = clone $this->date;
        }
    }

    /**
     * Converts this Page instance to a canonical Entry entity.
     *
     * @return \Indieinabox\Entry\Entry
     */
    public function toEntry(): \Indieinabox\Entry\Entry
    {
        $publishedAt = null;
        if ($this->date instanceof DateTime) {
            $publishedAt = \DateTimeImmutable::createFromMutable($this->date);
        }

        $author = [];
        if (!empty($this->metadata->author)) {
            $author['name'] = (string) $this->metadata->author;
        }
        if (!empty($this->metadata->nick)) {
            $author['handle'] = (string) $this->metadata->nick;
        }

        $lang = 'en';
        if (is_array($this->localization->lang)) {
            $lang = $this->localization->lang[0] ?? 'en';
        } elseif (is_string($this->localization->lang) && $this->localization->lang !== '') {
            $lang = $this->localization->lang;
        }

        $translations = [];
        $otherLangs = (array) ($this->localization->otherlang ?? []);
        $otherPaths = (array) ($this->localization->otherlangpath ?? []);
        foreach ($otherLangs as $idx => $code) {
            if (isset($otherPaths[$idx])) {
                $translations[(string) $code] = (string) $otherPaths[$idx];
            }
        }

        $attachments = [];
        foreach ((array) ($this->content->images ?? []) as $img) {
            if (is_string($img) && $img !== '') {
                $attachments[] = ['type' => 'image', 'url' => $img];
            }
        }

        $isDraft = in_array('draft', (array) ($this->metadata->tags ?? []), true);

        return new \Indieinabox\Entry\Entry([
            'id' => $this->slug,
            'slug' => $this->slug,
            'title' => $this->metadata->title ?? null,
            'content' => (string) ($this->content->content ?? ''),
            'rawContent' => (string) ($this->content->rawBody ?? ''),
            'publishedAt' => $publishedAt ?? new \DateTimeImmutable(),
            'sourceNetwork' => 'local',
            'sourceUrl' => 'local',
            'author' => $author,
            'syndicationTargets' => ['rss', 'atom', 'twtxt'],
            'lang' => $lang,
            'translations' => $translations,
            'kind' => (string) ($this->metadata->kind ?? 'note'),
            'tags' => (array) ($this->metadata->tags ?? []),
            'inReplyTo' => $this->metadata->in_reply_to ?? null,
            'attachments' => $attachments,
            'isDraft' => $isDraft,
            'metadata' => [
                'layout' => $this->metadata->layout ?? null,
                'hide_on_rss' => $this->metadata->hide_on_rss ?? false,
                'noauthor' => $this->metadata->noauthor ?? false,
            ],
        ]);
    }

    /**
     * Checks whether this page is marked as a draft.
     */
    public function isDraft(): bool
    {
        return in_array('draft', (array) ($this->metadata->tags ?? []), true);
    }

    /**
     * Checks whether this page has a custom title.
     */
    public function hasTitle(): bool
    {
        return !empty($this->metadata->title);
    }

    /**
     * Returns the title of the page with fallback translation.
     */
    public function getTitle(): string
    {
        return (string) $this->__get('title');
    }

    /**
     * Returns the canonical slug of the page.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Returns the taxonomy kind of the page.
     */
    public function getKind(): string
    {
        return (string) ($this->metadata->kind ?? 'note');
    }

    /**
     * Returns the language code of the page.
     */
    public function getLanguage(): string
    {
        return (string) ($this->localization->lang ?? 'en');
    }

    /**
     * Checks whether the page contains a specific tag.
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, (array) ($this->metadata->tags ?? []), true);
    }

    /**
     * Returns the publication date of the page.
     */
    public function getDate(): DateTime
    {
        return $this->date;
    }

    /**
     * Converts this Page instance into an associative array structure.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $dateStr = null;
        if ($this->date instanceof \DateTimeInterface) {
            $dateStr = $this->date->format('Y-m-d H:i:s');
        }

        $kind = $this->getKind();
        $lang = $this->getLanguage();
        $tags = (array) ($this->metadata->tags ?? []);
        $category = (array) ($this->metadata->category ?? []);

        return [
            'slug' => $this->slug,
            'relpath' => $this->relpath,
            'filepath' => $this->filepath,
            'title' => $this->getTitle(),
            'kind' => $kind,
            'lang' => $lang,
            'date' => $dateStr,
            'tags' => $tags,
            'category' => $category,
            'content' => (string) ($this->content->content ?? ''),
            'originalcontent' => (string) ($this->content->originalcontent ?? ''),
            'rawBody' => (string) ($this->content->rawBody ?? ''),
            'frontmatter' => [
                'title' => $this->metadata->title ?? null,
                'kind' => $kind,
                'lang' => $lang,
                'date' => $dateStr,
                'tags' => $tags,
                'category' => $category,
                'layout' => $this->metadata->layout ?? null,
            ],
        ];
    }
}
