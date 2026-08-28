<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Tests\Unit;

use MyTree\IndexProviders\Support\SequentialHtmlTableParser;
use MyTree\IndexProviders\Tests\TestCase;

final class SequentialHtmlTableParserTest extends TestCase
{
    public function testParsesAllWolynSectionsAndPreservesLinks(): void
    {
        $tables = (new SequentialHtmlTableParser())->parse($this->fixture('wolyn_small.html'));

        self::assertCount(4, $tables);
        self::assertSame('Zgony', $tables[0]['title']);
        self::assertCount(16, $tables[0]['headers']);
        self::assertSame('Urodzenia', $tables[1]['title']);
        self::assertCount(18, $tables[1]['headers']);
        self::assertSame('Śluby', $tables[2]['title']);
        self::assertCount(26, $tables[2]['headers']);
        self::assertSame('https://example.test/birth', $tables[1]['rows'][0]['hrefs'][17][0] ?? null);
    }
}
