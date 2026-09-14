<?php

declare(strict_types=1);

namespace Indieinabox\Support;

use Beautify_Html;
use Indieinabox\HtmlMinifier;

/**
 * Class HtmlUtils
 *
 * Provides formatting, beautification, and minification utilities for HTML output.
 */
class HtmlUtils
{
    /**
     * Formats and indents HTML output cleanly.
     *
     * @param string $html
     * @return string
     */
    public static function beautify(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        if (class_exists(Beautify_Html::class)) {
            $beautify = new Beautify_Html([
                'indent_inner_html' => false,
                'indent_char' => ' ',
                'indent_size' => 2,
                'wrap_line_length' => 32786,
                'unformatted' => ['code', 'pre'],
                'preserve_newlines' => false,
                'max_preserve_newlines' => 32786,
                'indent_scripts' => 'normal',
            ]);
            return $beautify->beautify($html);
        }

        return $html;
    }

    /**
     * Minifies HTML content by collapsing whitespace and stripping comments.
     *
     * @param string $html
     * @return string
     */
    public static function minify(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        if (class_exists(HtmlMinifier::class)) {
            $minifier = new HtmlMinifier([
                'collapse_whitespace' => true,
                'disable_comments' => true,
            ]);
            return $minifier->minify($html);
        }

        return $html;
    }
}
