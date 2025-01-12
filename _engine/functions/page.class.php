<?php

class Page
{
    public function __construct(
        public array $category = ["No Category"],
        public string $content = "Dummy content",
        public DateTime $date = new DateTime('now'),
        public array $images = [],
        public string $isodate = "2001-01-01T00:00Z",
        public string $kind = "note",
        public string $lang = "en",
        public string $langpath = "",
        public string $langslug = "untitled",
        public string $layout = "page",
        public string $localizeddate = "Saturday, January 1 of 2001, 00:00 UTC",
        public string $localizedkind = "note",
        public string $nick = "untitled",
        public bool $noauthor = false,
        public array $otherlang = [],
        public array $otherlangpath = [],
        public string $originalcontent = "untitled",
        public string $relpath = "",
        public string $slug = "untitled",
        public array $tags = ["No Tag"],
        public string $title = "Untitled"
    ) {}
}

class Pages extends ArrayObject
{
    private $a;
    public function __construct(array $a = [])
    {
        $this->a = $a;
    }
    public function add(Page $page, $id = null)
    {
        if ($id) {
            $this->a[] = $page;
        } else {
            $this->a[$id] = $page;
        }
    }
    public function getAll(): array
    {
        return $this->a;
    }
    public function get(int $id)
    {
        return $this->a[$id];
    }
}
