<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Support;

final class HtmlSelectParser
{
    /**
     * @return list<array{value:string,label:string}>
     */
    public function options(string $html, string $fieldName): array
    {
        $selectHtml = $this->findSelect($html, $fieldName);
        if ($selectHtml === null) {
            return [];
        }

        $result = [];
        if (!preg_match_all('~<option\b([^>]*)>(.*?)</option>~isu', $selectHtml, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            $attrs = $match[1];
            $label = Html::text($match[2]);
            $value = '';
            if (preg_match('~\bvalue\s*=\s*(["\'])(.*?)\1~isu', $attrs, $m)) {
                $value = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif (preg_match('~\bvalue\s*=\s*([^\s>]+)~isu', $attrs, $m)) {
                $value = html_entity_decode(trim($m[1], "\"'"), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            $result[] = ['value' => trim($value), 'label' => trim($label)];
        }

        return $result;
    }

    private function findSelect(string $html, string $fieldName): ?string
    {
        $cursor = 0;
        while (($start = stripos($html, '<select', $cursor)) !== false) {
            $openEnd = strpos($html, '>', $start);
            if ($openEnd === false) {
                return null;
            }
            $attrs = substr($html, $start, $openEnd - $start + 1);
            $end = stripos($html, '</select>', $openEnd);
            if ($end === false) {
                return null;
            }

            $name = $this->attribute($attrs, 'name');
            $id = $this->attribute($attrs, 'id');
            if ($name === $fieldName || $id === $fieldName) {
                return substr($html, $openEnd + 1, $end - $openEnd - 1);
            }
            $cursor = $end + strlen('</select>');
        }
        return null;
    }

    private function attribute(string $tag, string $name): ?string
    {
        if (preg_match('~\b' . preg_quote($name, '~') . '\s*=\s*(["\'])(.*?)\1~isu', $tag, $m)) {
            return html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (preg_match('~\b' . preg_quote($name, '~') . '\s*=\s*([^\s>]+)~isu', $tag, $m)) {
            return html_entity_decode(trim($m[1], "\"'"), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return null;
    }
}
