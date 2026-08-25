<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Provider;

use MyTree\IndexProviders\Support\Html;

final class GenetekaMetadataParser
{
    /** @return array<string,mixed> */
    public function parse(string $html): array
    {
        $out = [
            'html' => $html,
            'links' => Html::hrefs($html),
        ];

        if (preg_match('~i\.png["\'][^>]*\btitle=(["\'])(.*?)\1~isu', $html, $m)) {
            $note = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $out['notes_raw'] = trim($note);
            $out['notes_lines'] = array_values(array_filter(array_map(
                static fn (string $line): string => trim($line),
                preg_split('~[\r\n]+~u', $note) ?: [],
            ), static fn (string $line): bool => $line !== ''));
        }

        if (preg_match('~z\.png["\'][^>]*\btitle=(["\'])(.*?)\1~isu', $html, $m)) {
            $out['archive_description'] = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if (preg_match('~uname=([^"\'&]+)~isu', $html, $m)) {
            $out['indexed_by'] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('~fix\.php\?([^"\']+)~isu', $html, $m)) {
            parse_str(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $params);
            if (isset($params['gid'])) {
                $out['gid'] = (string) $params['gid'];
            }
            $out['correction_params'] = $params;
        }

        foreach ($this->anchors($html) as $anchor) {
            $marker = strtolower($anchor['image'] ?? '');
            if ($marker === 'z.png') {
                $out['archive_url'] = $anchor['href'];
            } elseif ($marker === 's.png') {
                $out['document_url'] = $anchor['href'];
            }
        }

        return $out;
    }

    /** @return list<array{href:string,image:?string}> */
    private function anchors(string $html): array
    {
        $out = [];
        if (!preg_match_all('~<a\b([^>]*)>(.*?)</a>~isu', $html, $matches, PREG_SET_ORDER)) {
            return $out;
        }
        foreach ($matches as $match) {
            $href = null;
            if (preg_match('~href\s*=\s*(["\'])(.*?)\1~isu', $match[1], $hm)) {
                $href = html_entity_decode($hm[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if ($href === null) {
                continue;
            }
            $image = null;
            if (preg_match('~<img\b[^>]*src\s*=\s*(["\'])(.*?)\1~isu', $match[2], $im)) {
                $image = basename(parse_url(html_entity_decode($im[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_PATH) ?: $im[2]);
            }
            $out[] = ['href' => $href, 'image' => $image];
        }
        return $out;
    }
}
