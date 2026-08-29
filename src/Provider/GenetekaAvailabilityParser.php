<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Provider;

use MyTree\IndexProviders\Domain\RecordType;
use MyTree\IndexProviders\Domain\YearRange;

final class GenetekaAvailabilityParser
{
    /** @return array<string,string> record type value => provider rid */
    public function recordTypeIds(string $html): array
    {
        $result = [];
        foreach ($this->recordTypeLinks($html) as $link) {
            $result[$link['type']->value] = $link['rid'];
        }
        return $result;
    }

    /** @return list<YearRange> */
    public function yearRanges(string $html): array
    {
        $links = $this->recordTypeLinks($html);
        $firstTypeLinkOffset = $links !== [] ? min(array_column($links, 'offset')) : null;

        $prefix = $firstTypeLinkOffset !== null ? substr($html, 0, $firstTypeLinkOffset) : $html;
        $marker = strripos($prefix, 'Jak indeks');
        $candidate = $marker !== false ? substr($prefix, $marker) : substr($prefix, -3000);
        // Preserve separators carried only by HTML markup (especially <br>).
        // strip_tags() alone would turn `1645<br>1654` into `16451654`.
        $candidate = preg_replace('~<(?:br|hr)\b[^>]*>~i', ' ', $candidate) ?? $candidate;
        $candidate = preg_replace('~</(?:div|p|li|td|th|tr|section|article)>~i', ' ', $candidate) ?? $candidate;
        $text = html_entity_decode(strip_tags($candidate), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\u{2013}", "\u{2014}"], '-', $text);

        preg_match_all('~(?<!\d)(\d{4})(?:\s*-\s*(\d{4}))?(?!\d)~u', $text, $matches, PREG_SET_ORDER);
        $ranges = [];
        $seen = [];
        foreach ($matches as $match) {
            $from = (int) $match[1];
            $to = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : $from;
            if ($from < 1 || $to > 9999 || $from > $to) {
                continue;
            }
            $key = $from . ':' . $to;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $ranges[] = new YearRange($from, $to);
            }
        }

        usort($ranges, static fn (YearRange $a, YearRange $b): int => [$a->from, $a->to] <=> [$b->from, $b->to]);
        return $ranges;
    }

    /**
     * Reads only navigation links representing Geneteka record-type tabs.
     * Links such as fix.php?gid=...&bdm=... are intentionally ignored.
     *
     * @return list<array{offset:int,type:RecordType,rid:string}>
     */
    private function recordTypeLinks(string $html): array
    {
        if (!preg_match_all(
            '~<a\b[^>]*\bhref\s*=\s*(["\'])([^"\']*)\1[^>]*>~is',
            $html,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        )) {
            return [];
        }

        $result = [];
        foreach ($matches as $match) {
            $href = html_entity_decode((string) ($match[2][0] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $offset = $match[0][1] ?? null;
            if ($href === '' || !is_int($offset)) {
                continue;
            }
            $query = parse_url($href, PHP_URL_QUERY);
            if (!is_string($query)) {
                continue;
            }
            parse_str($query, $params);
            if (strtolower(trim((string) ($params['op'] ?? ''))) !== 'gt') {
                continue;
            }
            $type = $this->recordTypeFromProviderCode(strtoupper(trim((string) ($params['bdm'] ?? ''))));
            $rid = trim((string) ($params['rid'] ?? ''));
            if ($type === null || $rid === '') {
                continue;
            }
            $result[] = ['offset' => $offset, 'type' => $type, 'rid' => $rid];
        }

        return $result;
    }

    private function recordTypeFromProviderCode(string $code): ?RecordType
    {
        return match ($code) {
            'B' => RecordType::Birth,
            'S' => RecordType::Marriage,
            'D' => RecordType::Death,
            default => null,
        };
    }
}
