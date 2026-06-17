<?php

declare(strict_types=1);

namespace Indieinabox\Markdown;

interface RendererInterface
{
    /**
     * Renders a given AST Node to a string format.
     *
     * @param Node $node
     * @return string
     */
    public function render(Node $node): string;
}
