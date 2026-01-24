<?php

declare(strict_types=1);

namespace Indieinabox\Parsedown;

final class ElementsHandler
{
    private bool $markupEscaped = false;
    private bool $safeMode = false;
    private array $safeLinksWhitelist = [
        // ... (same as original)
    ];

    public function setMarkupEscaped(bool $markupEscaped): void
    {
        $this->markupEscaped = $markupEscaped;
    }

    public function setSafeMode(bool $safeMode): void
    {
        $this->safeMode = $safeMode;
    }

    public function element(array $element): string
    {
        // ... (same as original)
    }

    public function elements(array $elements): string
    {
        // ... (same as original)
    }

    // ... (other element-related methods)
}
