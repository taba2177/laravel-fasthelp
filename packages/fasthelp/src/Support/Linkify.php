<?php

namespace Tabadev\FastHelp\Support;

class Linkify
{
    /**
     * HTML-escape the input text, then replace bare http(s) URLs with clickable anchors.
     *
     * The method operates on the already-escaped string so no raw HTML can be injected
     * through the URL. Trailing punctuation is trimmed outside the anchor.
     *
     * @return string HTML-safe string intended for {!! !!} output.
     */
    public static function toHtml(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // Escape first — this turns <, >, ", & into entities and ensures the text
        // is safe. URLs only contain characters that survive htmlspecialchars intact
        // (no angle brackets, quotes or spaces), so the regex below still matches them.
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Match http(s) URLs on the already-escaped string.
        // URL-safe chars: anything except whitespace, <, >, " (which are now entities).
        $pattern = '#\bhttps?://[^\s<>"]+#i';

        return preg_replace_callback($pattern, static function (array $m): string {
            $raw = $m[0];

            // Trim trailing punctuation that is unlikely to be part of the URL.
            $url = rtrim($raw, '.,;:!?)\'');

            // Build the anchor. $url is already HTML-escaped (came from escaped string).
            return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer">'.$url.'</a>'
                .substr($raw, strlen($url));
        }, $escaped) ?? $escaped;
    }
}
