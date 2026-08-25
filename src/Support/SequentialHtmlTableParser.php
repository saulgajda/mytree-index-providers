<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Support;

use RuntimeException;

/**
 * Minimal deterministic parser for the simple server-rendered tables used by Metryki-Wołyń.
 * It intentionally avoids matching an entire multi-megabyte <table> with one PCRE expression.
 */
final class SequentialHtmlTableParser
{
    /**
     * @return list<array{title:string,headers:list<string>,rows:list<array{cells:list<string>,html:list<string>,hrefs:list<list<string>>}>}>
     */
    public function parse(string $html): array
    {
        $tables = [];
        if (!preg_match_all('~<h2\b[^>]*>(.*?)</h2>~isu', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        foreach ($matches[0] as $i => $fullMatch) {
            $headingHtml = $matches[1][$i][0];
            $headingOffset = $fullMatch[1] + strlen($fullMatch[0]);
            $tableStart = stripos($html, '<table', $headingOffset);
            if ($tableStart === false) {
                continue;
            }
            $tableOpenEnd = strpos($html, '>', $tableStart);
            $tableEnd = stripos($html, '</table>', $tableOpenEnd === false ? $tableStart : $tableOpenEnd);
            if ($tableOpenEnd === false || $tableEnd === false) {
                throw new RuntimeException('Malformed HTML table after heading: ' . Html::text($headingHtml));
            }

            $rows = [];
            $cursor = $tableOpenEnd + 1;
            while (true) {
                $trStart = stripos($html, '<tr', $cursor);
                if ($trStart === false || $trStart >= $tableEnd) {
                    break;
                }
                $trOpenEnd = strpos($html, '>', $trStart);
                $trEnd = stripos($html, '</tr>', $trOpenEnd === false ? $trStart : $trOpenEnd);
                if ($trOpenEnd === false || $trEnd === false || $trEnd > $tableEnd) {
                    break;
                }
                $rowHtml = substr($html, $trOpenEnd + 1, $trEnd - $trOpenEnd - 1);
                $rows[] = $this->parseRow($rowHtml);
                $cursor = $trEnd + strlen('</tr>');
            }

            if ($rows === []) {
                continue;
            }
            $header = array_shift($rows);
            $tables[] = [
                'title' => Html::text($headingHtml),
                'headers' => $header['cells'],
                'rows' => $rows,
            ];
        }

        return $tables;
    }

    /** @return array{cells:list<string>,html:list<string>,hrefs:list<list<string>>} */
    private function parseRow(string $rowHtml): array
    {
        $cells = [];
        $cellHtml = [];
        $hrefs = [];
        $cursor = 0;
        $length = strlen($rowHtml);

        while ($cursor < $length) {
            if (!preg_match('~<t([dh])\b[^>]*>~isu', $rowHtml, $m, PREG_OFFSET_CAPTURE, $cursor)) {
                break;
            }
            $open = $m[0][0];
            $start = $m[0][1];
            $kind = strtolower($m[1][0]);
            $contentStart = $start + strlen($open);
            $closeTag = '</t' . $kind . '>';
            $end = stripos($rowHtml, $closeTag, $contentStart);
            if ($end === false) {
                break;
            }
            $content = substr($rowHtml, $contentStart, $end - $contentStart);
            $cellHtml[] = $content;
            $cells[] = Html::text($content);
            $hrefs[] = Html::hrefs($content);
            $cursor = $end + strlen($closeTag);
        }

        return ['cells' => $cells, 'html' => $cellHtml, 'hrefs' => $hrefs];
    }
}
