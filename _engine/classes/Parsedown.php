<?php
//
//
// Parsedown
// http://parsedown.org
//
// (c) Emanuil Rusev
// http://erusev.com
//
// For the full license information, view the LICENSE file that was distributed
// with this source code.
//
//
declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Parsedown\BlocksParser;
use Indieinabox\Parsedown\InlinesParser;
use Indieinabox\Parsedown\ElementsHandler;

final class Parsedown
{
    private BlocksParser $blocksParser;
    private InlinesParser $inlinesParser;
    private ElementsHandler $elementsHandler;

    public function __construct()
    {
        $this->blocksParser = new BlocksParser();
        $this->inlinesParser = new InlinesParser();
        $this->elementsHandler = new ElementsHandler();
    }

    public function text(string $text): string
    {
        $this->blocksParser->resetDefinitionData();
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text, "\n");
        $lines = explode("\n", $text);
        $markup = $this->blocksParser->lines($lines);
        return trim($markup, "\n");
    }

    public function setBreaksEnabled(bool $breaksEnabled): self
    {
        $this->inlinesParser->setBreaksEnabled($breaksEnabled);
        return $this;
    }

    public function setMarkupEscaped(bool $markupEscaped): self
    {
        $this->elementsHandler->setMarkupEscaped($markupEscaped);
        return $this;
    }

    public function setUrlsLinked(bool $urlsLinked): self
    {
        $this->inlinesParser->setUrlsLinked($urlsLinked);
        return $this;
    }

    public function setSafeMode(bool $safeMode): self
    {
        $this->elementsHandler->setSafeMode($safeMode);
        return $this;
    }
}
