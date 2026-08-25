<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Support;

/**
 * Sequential scanner for ordinary HTML tables. It never matches a full table with one PCRE.
 */
final class SimpleHtmlTableScanner
{
    /**
     * @return list<array{headers:list<string>,rows:list<list<string>>}>
     */
    public function scan(string $html): array
    {
        $tables = [];
        $cursor = 0;
        while (($tableStart = stripos($html, '<table', $cursor)) !== false) {
            $openEnd = strpos($html, '>', $tableStart);
            if ($openEnd === false) {
                break;
            }
            $tableEnd = stripos($html, '</table>', $openEnd);
            if ($tableEnd === false) {
                break;
            }

            $rows = [];
            $rowCursor = $openEnd + 1;
            while (($trStart = stripos($html, '<tr', $rowCursor)) !== false && $trStart < $tableEnd) {
                $trOpenEnd = strpos($html, '>', $trStart);
                if ($trOpenEnd === false || $trOpenEnd >= $tableEnd) {
                    break;
                }
                $trEnd = stripos($html, '</tr>', $trOpenEnd);
                if ($trEnd === false || $trEnd > $tableEnd) {
                    break;
                }
                $rowHtml = substr($html, $trOpenEnd + 1, $trEnd - $trOpenEnd - 1);
                $cells = $this->cells($rowHtml);
                if ($cells !== []) {
                    $rows[] = $cells;
                }
                $rowCursor = $trEnd + strlen('</tr>');
            }

            if ($rows !== []) {
                $headers = $rows[0];
                $tables[] = ['headers' => $headers, 'rows' => array_slice($rows, 1)];
            }
            $cursor = $tableEnd + strlen('</table>');
        }
        return $tables;
    }

    /** @return list<string> */
    private function cells(string $rowHtml): array
    {
        $cells = [];
        $cursor = 0;
        $length = strlen($rowHtml);
        while ($cursor < $length && preg_match('~<t([dh])\b[^>]*>~isu', $rowHtml, $m, PREG_OFFSET_CAPTURE, $cursor)) {
            $open = $m[0][0];
            $start = $m[0][1];
            $kind = strtolower($m[1][0]);
            $contentStart = $start + strlen($open);
            $close = '</t' . $kind . '>';
            $end = stripos($rowHtml, $close, $contentStart);
            if ($end === false) {
                break;
            }
            $cells[] = Html::text(substr($rowHtml, $contentStart, $end - $contentStart));
            $cursor = $end + strlen($close);
        }
        return $cells;
    }
}
