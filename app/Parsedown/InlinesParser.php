<?php

declare(strict_types=1);

namespace Indieinabox\Parsedown;

final class InlinesParser
{
    private $breaksEnabled = false;
    private $urlsLinked = true;
    private $inlineTypes = [
        // ... (same as original)
    ];
    private $inlineMarkerList = '!"*_&[:<>`~\\';

    public function setBreaksEnabled(bool $breaksEnabled): void
    {
        $this->breaksEnabled = $breaksEnabled;
    }

    public function setUrlsLinked(bool $urlsLinked): void
    {
        $this->urlsLinked = $urlsLinked;
    }

    public function line(string $text, array $nonNestables = []): string
    {
        // ... (same as original)
    }

    // ... (other inline-related methods)
}
