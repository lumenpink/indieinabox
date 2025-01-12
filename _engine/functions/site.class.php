<?php

class Site
{
    public function __construct(
        public $basedir = "/",
        public $title = "My Site",
        public $sitename = "My Site",
        public $author = "Me",
        public $defaulttitle = "Untitled",
        public $support = ["md", "txt", "html", "htm"],
        public $buildall = true,
        public $outputdir = "_site",
        public $contentdir = "_content",
        public $defaultcategory = "General",
        public $lang = ["en"],
        public $defaultlang = "en",
        public $fqdn = "http://localhost:8080",
        public $htmlpostprocessing = ["minify"],
        public $dev = false,
        public $skipstatic = false
    ) {}
}
