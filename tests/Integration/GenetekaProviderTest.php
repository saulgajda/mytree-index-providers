<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Tests\Integration;

use MyTree\IndexProviders\Domain\HttpResponse;
use MyTree\IndexProviders\Domain\RecordType;
use MyTree\IndexProviders\Provider\GenetekaProvider;
use MyTree\IndexProviders\Storage\JsonCheckpointStore;
use MyTree\IndexProviders\Storage\RawResponseStore;
use MyTree\IndexProviders\Support\RateLimiter;
use MyTree\IndexProviders\Tests\Support\FakeHttpClient;
use MyTree\IndexProviders\Tests\TestCase;
use MyTree\IndexProviders\Writer\JsonlWriter;

final class GenetekaProviderTest extends TestCase
{
    public function testMapsCanonicalRecordTypesToProviderSpecificBdmCodes(): void
    {
        $body = json_encode(
            ['recordsTotal' => '0', 'recordsFiltered' => '0', 'data' => []],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $http = new FakeHttpClient(fn (string $url): HttpResponse => new HttpResponse(200, [], $body, $url));
        $dir = $this->tmp . '/gen-type-mapping';
        $writer = new JsonlWriter($dir . '/records.jsonl', false);
        $provider = $this->provider($http, $dir);

        $provider->acquire(
            '06mp',
            '4812',
            'Imbramowice',
            [RecordType::Birth, RecordType::Marriage, RecordType::Death],
            $writer,
        );
        $writer->close();

        self::assertCount(3, $http->urls);
        self::assertStringContainsString('bdm=B', $http->urls[0]);
        self::assertStringContainsString('bdm=S', $http->urls[1]);
        self::assertStringContainsString('bdm=D', $http->urls[2]);
    }

    public function testMapsBirthRecordAndResumesFromCheckpoint(): void
    {
        $body = json_encode(
            ['recordsTotal' => '1', 'recordsFiltered' => '1', 'data' => [$this->birthRow()]],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $http = new FakeHttpClient(fn (string $url): HttpResponse => new HttpResponse(200, [], $body, $url));
        $dir = $this->tmp . '/gen';
        $writer = new JsonlWriter($dir . '/records.jsonl', false);
        $provider = $this->provider($http, $dir);

        $stats = $provider->acquire('06mp', '4812', 'Imbramowice', [RecordType::Birth], $writer);
        $writer->close();

        self::assertSame(1, $stats->records);
        self::assertSame(1, $http->calls);
        self::assertStringContainsString('bdm=B', $http->urls[0]);

        $record = json_decode((string) file_get_contents($dir . '/records.jsonl'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('12309516', $record['provider_record_id'] ?? null);
        self::assertSame('Dzjurbjel', $record['fields']['person']['mother_surname_raw'] ?? null);
        self::assertSame('indexer_rendering', $record['representation']['kind'] ?? null);
        self::assertSame('karpecki.lukasz', $record['representation']['producer']['indexer_id'] ?? null);
        self::assertFalse($record['representation']['original_document_wording_asserted'] ?? true);

        $resumeWriter = new JsonlWriter($dir . '/records.jsonl', true);
        $resumeProvider = $this->provider($http, $dir);
        $resumeProvider->acquire('06mp', '4812', 'Imbramowice', [RecordType::Birth], $resumeWriter);
        $resumeWriter->close();

        self::assertSame(1, $http->calls, 'Resume should use checkpoint and make no extra request.');
    }

    private function provider(FakeHttpClient $http, string $dir): GenetekaProvider
    {
        return new GenetekaProvider(
            $http,
            new JsonCheckpointStore($dir . '/state.json'),
            new RawResponseStore($dir . '/raw'),
            new RateLimiter(0),
        );
    }

    /** @return list<string> */
    private function birthRow(): array
    {
        return [
            '1913',
            '',
            'Anna ',
            'Tiliszczak',
            'Michał',
            'Melania',
            'Dzjurbjel',
            'Imbramowice',
            'Imbramowice',
            $this->stuff(),
        ];
    }

    private function stuff(): string
    {
        return '<img src="images/i.png" title="Uwagi: Rodzice ojca: Ilja i Tacijanna Tiliszczak. &#013;Data urodzenia: 14.08.1913 r. "><a href="http://www.przemysl.ap.gov.pl/" target="_blank"><img src="images/z.png" title="Miejsce przechowywania ksiąg"></a><a target="_blank" href="https://www.genealodzy.pl/user.php?op=userinfo&amp;uname=karpecki.lukasz"><img src="images/a.png"></a><a target="doc" href="https://www.szukajwarchiwach.gov.pl/jednostka/-/jednostka/18065584"><img src="images/s.png"></a><a href="fix.php?gid=12309516&amp;bdm=B&amp;w=06mp&amp;rid=16033"><img src="images/fix.png"></a>';
    }
}
