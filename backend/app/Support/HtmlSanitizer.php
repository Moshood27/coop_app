<?php

namespace App\Support;

class HtmlSanitizer
{
    /**
     * Clean HTML content to prevent XSS while preserving safe formatting tags.
     */
    public static function clean(?string $html, string $allowedTags = '<p><br><b><i><u><ul><ol><li><a><h1><h2><h3><h4><h5><h6>'): string
    {
        if (empty($html)) {
            return '';
        }

        // 1. Strip disallowed tags
        $cleaned = strip_tags($html, $allowedTags);

        // 2. Remove dangerous attributes (on*, javascript:, data:, style)
        // Remove all on* attributes (e.g. onclick, onerror)
        $cleaned = preg_replace('/\s+on\w+\s*=\s*(["\'])(?:(?!\1).)*\1/i', '', $cleaned);
        $cleaned = preg_replace('/\s+on\w+\s*=[^\s>]+/i', '', $cleaned);

        // Remove javascript: and data: from href and src to prevent execution
        $cleaned = preg_replace('/(href|src)\s*=\s*(["\'])\s*(?:javascript|data):(?:(?!\2).)*\2/i', '$1="#"', $cleaned);
        $cleaned = preg_replace('/(href|src)\s*=\s*(?:javascript|data):[^\s>]+/i', '$1="#"', $cleaned);

        // Remove style attribute to prevent CSS-based XSS (e.g. expression() or url(javascript:...))
        $cleaned = preg_replace('/\s+style\s*=\s*(["\'])(?:(?!\1).)*\1/i', '', $cleaned);
        $cleaned = preg_replace('/\s+style\s*=[^\s>]+/i', '', $cleaned);

        return $cleaned;
    }

    /**
     * Specifically clean SVG icons, allowing only safe SVG elements.
     */
    public static function cleanSvg(?string $svg): string
    {
        if (empty($svg)) {
            return '';
        }

        // Allow only core SVG drawing elements
        $allowedTags = '<svg><path><circle><rect><line><polyline><polygon><g><defs><clipPath><linearGradient><stop><text><tspan>';
        $cleaned = strip_tags($svg, $allowedTags);

        // Remove event handlers and scripts
        $cleaned = preg_replace('/\s+on\w+\s*=\s*(["\'])(?:(?!\1).)*\1/i', '', $cleaned);
        $cleaned = preg_replace('/\s+on\w+\s*=[^\s>]+/i', '', $cleaned);

        // Remove any href that uses javascript:
        $cleaned = preg_replace('/\s+(?:xlink:)?href\s*=\s*(["\'])\s*javascript:(?:(?!\1).)*\1/i', '', $cleaned);

        // Remove <script> blocks explicitly just in case
        $cleaned = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $cleaned);

        return $cleaned;
    }
}
