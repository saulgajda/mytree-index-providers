<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Support;

final class Html
{
    public static function text(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('~\s+~u', ' ', $text) ?? $text);
    }

    public static function firstHref(string $html): ?string
    {
        if (!preg_match('~href\s*=\s*(["\'])(.*?)\1~isu', $html, $m)) {
            return null;
        }
        return html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @return list<string> */
    public static function hrefs(string $html): array
    {
        if (!preg_match_all('~href\s*=\s*(["\'])(.*?)\1~isu', $html, $matches)) {
            return [];
        }
        return array_values(array_map(
            static fn (string $v): string => html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $matches[2],
        ));
    }
}
