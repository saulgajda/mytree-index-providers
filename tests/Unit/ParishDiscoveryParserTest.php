<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Tests\Unit;

use MyTree\IndexProviders\Provider\WolynParishListParser;
use MyTree\IndexProviders\Support\HtmlSelectParser;
use MyTree\IndexProviders\Tests\TestCase;

final class ParishDiscoveryParserTest extends TestCase
{
    public function testGenetekaParserReadsParishOptions(): void
    {
        $options = (new HtmlSelectParser())->options($this->fixture('geneteka_parishes.html'), 'rid');

        self::assertCount(3, $options);
        self::assertSame('4812', $options[1]['value'] ?? null);
    }

    public function testWolynParserReturnsParishesAndCoverage(): void
    {
        $parishes = (new WolynParishListParser())->parse(
            $this->fixture('wolyn_content.html'),
            'https://example.test/zawartosc',
        );

        self::assertCount(2, $parishes);
        self::assertSame('Szumsk', $parishes[1]->name ?? null);
        self::assertSame('1731-1926', $parishes[1]->metadata['wpisy']['births'] ?? null);
    }
}
