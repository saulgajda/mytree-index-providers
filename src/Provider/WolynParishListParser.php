<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Provider;

use MyTree\IndexProviders\Domain\AvailableParish;
use MyTree\IndexProviders\Support\SimpleHtmlTableScanner;

final class WolynParishListParser
{
    public function __construct(private readonly SimpleHtmlTableScanner $scanner = new SimpleHtmlTableScanner())
    {
    }

    /** @return list<AvailableParish> */
    public function parse(string $html, string $sourceUrl): array
    {
        foreach ($this->scanner->scan($html) as $table) {
            $header0 = $this->norm($table['headers'][0] ?? '');
            if (!str_contains($header0, 'parafia') && !str_contains($header0, 'parish')) {
                continue;
            }

            $result = [];
            $current = null;
            foreach ($table['rows'] as $row) {
                $first = trim($row[0] ?? '');
                if ($first === '') {
                    continue;
                }
                $kind = $this->norm($first);
                if (in_array($kind, ['wpisy', 'indeksy'], true)) {
                    if ($current !== null) {
                        $current['metadata'][$kind] = [
                            'births' => trim($row[1] ?? ''),
                            'marriages' => trim($row[2] ?? ''),
                            'deaths' => trim($row[3] ?? ''),
                            'parish_census' => trim($row[4] ?? ''),
                        ];
                    }
                    continue;
                }

                if ($current !== null && (isset($current['metadata']['wpisy']) || isset($current['metadata']['indeksy']))) {
                    $result[] = new AvailableParish(
                        provider: 'wolyn-metryki',
                        name: $current['name'],
                        metadata: $current['metadata'],
                    );
                }
                $current = [
                    'name' => $first,
                    'metadata' => [
                        'source_url' => $sourceUrl,
                    ],
                ];
            }

            if ($current !== null && (isset($current['metadata']['wpisy']) || isset($current['metadata']['indeksy']))) {
                $result[] = new AvailableParish(
                    provider: 'wolyn-metryki',
                    name: $current['name'],
                    metadata: $current['metadata'],
                );
            }

            return $this->dedupe($result);
        }
        return [];
    }

    /** @param list<AvailableParish> $items @return list<AvailableParish> */
    private function dedupe(array $items): array
    {
        $seen = [];
        $out = [];
        foreach ($items as $item) {
            $key = $this->norm($item->name);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $item;
        }
        usort($out, static fn (AvailableParish $a, AvailableParish $b): int => strnatcasecmp($a->name, $b->name));
        return $out;
    }

    private function norm(string $value): string
    {
        return strtolower(trim(preg_replace('~\s+~u', ' ', $value) ?? $value));
    }
}
