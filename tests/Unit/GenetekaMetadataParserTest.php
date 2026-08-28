<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Tests\Unit;

use MyTree\IndexProviders\Provider\GenetekaMetadataParser;
use MyTree\IndexProviders\Tests\TestCase;

final class GenetekaMetadataParserTest extends TestCase
{
    public function testExtractsDiagnosticMetadata(): void
    {
        $metadata = (new GenetekaMetadataParser())->parse($this->stuff());

        self::assertSame('12309516', $metadata['gid'] ?? null);
        self::assertSame('karpecki.lukasz', $metadata['indexed_by'] ?? null);
        self::assertContains(
            $metadata['document_url'] ?? null,
            [
                'https://www.szukajwarchiwow.gov.pl/jednostka/-/jednostka/18065584',
                'https://www.szukajwarchiwach.gov.pl/jednostka/-/jednostka/18065584',
            ],
        );
    }

    private function stuff(): string
    {
        return '<img src="images/i.png" title="Uwagi: Rodzice ojca: Ilja i Tacijanna Tiliszczak. &#013;Data urodzenia: 14.08.1913 r. "><a href="http://www.przemysl.ap.gov.pl/" target="_blank"><img src="images/z.png" title="Miejsce przechowywania ksiąg"></a><a target="_blank" href="https://www.genealodzy.pl/user.php?op=userinfo&amp;uname=karpecki.lukasz"><img src="images/a.png"></a><a target="doc" href="https://www.szukajwarchiwach.gov.pl/jednostka/-/jednostka/18065584"><img src="images/s.png"></a><a href="fix.php?gid=12309516&amp;bdm=B&amp;w=06mp&amp;rid=16033"><img src="images/fix.png"></a>';
    }
}
