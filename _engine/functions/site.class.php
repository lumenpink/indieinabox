<?php

class Site
{
    /** @var string */
    public $basedir;

    /** @var string */
    public $title;

    /** @var string */
    public $sitename;

    /** @var string */
    public $author;

    /** @var string */
    public $defaulttitle;

    /** @var array<string> */
    public $support;

    /** @var bool */
    public $buildall;

    /** @var string */
    public $outputdir;

    /** @var string */
    public $contentdir;

    /** @var string */
    public $defaultcategory;

    /** @var array<string> */
    public $lang;

    /** @var string */
    public $defaultlang;

    /** @var string */
    public $fqdn;

    /** @var string */
    public $htmlpostprocessing;

    /** @var bool */
    public $dev;

    /** @var bool */
    public $skipstatic;

    /** @var bool */
    public $forcestaticoverride;


    /** @var array */
    public $types;

    public function __construct(
        string $basedir = "/",
        string $title = "My Site",
        string $sitename = "My Site",
        string $author = "Me",
        string $defaulttitle = "Untitled",
        array $support = ["md", "txt", "html", "htm"],
        bool $buildall = true,
        string $outputdir = "_site",
        string $contentdir = "_content",
        string $defaultcategory = "General",
        array $lang = ["en"],
        string $defaultlang = "en",
        string $fqdn = "http://localhost:8080",
        string $htmlpostprocessing = "minify",
        bool $dev = false,
        bool $skipstatic = false,
        bool $forcestaticoverride = false,
        array $types = [],
    ) {
        $this->basedir = $basedir;
        $this->title = $title;
        $this->sitename = $sitename;
        $this->author = $author;
        $this->defaulttitle = $defaulttitle;
        $this->support = $support;
        $this->buildall = $buildall;
        $this->outputdir = $outputdir;
        $this->contentdir = $contentdir;
        $this->defaultcategory = $defaultcategory;
        $this->lang = $lang;
        $this->defaultlang = $defaultlang;
        $this->fqdn = $fqdn;
        $this->htmlpostprocessing = $htmlpostprocessing;
        $this->dev = $dev;
        $this->skipstatic = $skipstatic;
        $this->forcestaticoverride = $forcestaticoverride;
        $this->types = $types;
    }
}
