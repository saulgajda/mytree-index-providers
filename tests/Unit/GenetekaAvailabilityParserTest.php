<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Tests\Unit;

use MyTree\IndexProviders\Provider\GenetekaAvailabilityParser;
use PHPUnit\Framework\TestCase;

final class GenetekaAvailabilityParserTest extends TestCase
{
    public function testParsesProviderIdsAndYearRangesWithoutFlatteningGaps(): void
    {
        $html = <<<'HTML'
<html><body>
<a href="help">Jak indeksować</a>
<div>1645<br>1654<br/>1656<br />1658-1862<br>1868–1907</div>
<a href="fix.php?gid=999&amp;bdm=B&amp;w=10pl&amp;rid=9999">Popraw</a>
<a href="?op=gt&amp;bdm=B&amp;w=10pl&amp;rid=4257">Urodzenia</a>
<a href="?op=gt&amp;bdm=S&amp;w=10pl&amp;rid=10552">Małżeństwa</a>
<a href="?op=gt&amp;bdm=D&amp;w=10pl&amp;rid=8629">Zgony</a>
</body></html>
HTML;
        $parser = new GenetekaAvailabilityParser();

        self::assertSame([
            'birth' => '4257',
            'marriage' => '10552',
            'death' => '8629',
        ], $parser->recordTypeIds($html));
        self::assertSame([
            [1645, 1645],
            [1654, 1654],
            [1656, 1656],
            [1658, 1862],
            [1868, 1907],
        ], array_map(
            static fn ($range): array => [$range->from, $range->to],
            $parser->yearRanges($html),
        ));
    }
}
