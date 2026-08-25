<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MyTree\IndexProviders\Contracts\HttpClientInterface;
use MyTree\IndexProviders\Domain\HttpResponse;
use MyTree\IndexProviders\Provider\GenetekaMetadataParser;
use MyTree\IndexProviders\Provider\GenetekaProvider;
use MyTree\IndexProviders\Provider\WolynMetrykiProvider;
use MyTree\IndexProviders\Provider\WolynParishListParser;
use MyTree\IndexProviders\Storage\JsonCheckpointStore;
use MyTree\IndexProviders\Storage\RawResponseStore;
use MyTree\IndexProviders\Support\HtmlSelectParser;
use MyTree\IndexProviders\Support\RateLimiter;
use MyTree\IndexProviders\Support\SequentialHtmlTableParser;
use MyTree\IndexProviders\Writer\JsonlWriter;

final class FakeHttpClient implements HttpClientInterface
{
    public int $calls = 0;

    /** @param callable(string):HttpResponse $responder */
    public function __construct(private $responder) {}

    public function get(string $url, array $headers = []): HttpResponse
    {
        $this->calls++;
        return ($this->responder)($url);
    }
}

$failures = 0;
function ok(bool $condition, string $message): void
{
    global $failures;
    if ($condition) {
        echo "[OK] $message\n";
    } else {
        echo "[FAIL] $message\n";
        $failures++;
    }
}

$tmp = sys_get_temp_dir() . '/mytree-index-providers-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);

// Large-table-safe sequential parser basics.
$html = (string) file_get_contents(__DIR__ . '/fixtures/wolyn_small.html');
$parser = new SequentialHtmlTableParser();
$tables = $parser->parse($html);
ok(count($tables) === 4, 'Wołyń parser finds four sections.');
ok($tables[0]['title'] === 'Zgony' && count($tables[0]['headers']) === 16, 'Death table has 16 columns.');
ok($tables[1]['title'] === 'Urodzenia' && count($tables[1]['headers']) === 18, 'Birth table has 18 columns.');
ok($tables[2]['title'] === 'Śluby' && count($tables[2]['headers']) === 26, 'Marriage table has 26 columns.');
ok(($tables[1]['rows'][0]['hrefs'][17][0] ?? null) === 'https://example.test/birth', 'Cell links are preserved.');

// Parish discovery parsers.
$genParishHtml = (string) file_get_contents(__DIR__ . '/fixtures/geneteka_parishes.html');
$selectParser = new HtmlSelectParser();
$ridOptions = $selectParser->options($genParishHtml, 'rid');
ok(count($ridOptions) === 3 && ($ridOptions[1]['value'] ?? null) === '4812', 'Geneteka parish select parser reads rid options.');

$wolynContentHtml = (string) file_get_contents(__DIR__ . '/fixtures/wolyn_content.html');
$wolynParishParser = new WolynParishListParser();
$wolynParishes = $wolynParishParser->parse($wolynContentHtml, 'https://example.test/zawartosc');
ok(count($wolynParishes) === 2, 'Wołyń content parser ignores section labels and returns two parishes.');
ok(($wolynParishes[1]->name ?? null) === 'Szumsk', 'Wołyń content parser returns Szumsk.');
ok(($wolynParishes[1]->metadata['wpisy']['births'] ?? null) === '1731-1926', 'Wołyń content parser keeps coverage ranges.');

// Provider-level parish discovery with fake HTTP.
$genDiscoveryHttp = new FakeHttpClient(fn (string $url): HttpResponse => new HttpResponse(200, [], $genParishHtml, $url));
$genDiscoveryDir = $tmp . '/gen-discovery';
$genDiscovery = new GenetekaProvider(
    $genDiscoveryHttp,
    new JsonCheckpointStore($genDiscoveryDir . '/state.json'),
    new RawResponseStore($genDiscoveryDir . '/raw'),
    new RateLimiter(0),
);
$regions = $genDiscovery->listRegions();
ok(count($regions) === 2 && ($regions[0]['code'] ?? null) === '06mp', 'Geneteka provider discovers regions.');
$genParishes = $genDiscovery->listParishes('06mp');
ok(count($genParishes) === 2 && ($genParishes[0]->providerParishId ?? null) === '4812', 'Geneteka provider discovers numeric parish ids.');

$wolynDiscoveryHttp = new FakeHttpClient(fn (string $url): HttpResponse => new HttpResponse(200, [], $wolynContentHtml, $url));
$wolynDiscoveryDir = $tmp . '/wolyn-discovery';
$wolynDiscovery = new WolynMetrykiProvider(
    $wolynDiscoveryHttp,
    new JsonCheckpointStore($wolynDiscoveryDir . '/state.json'),
    new RawResponseStore($wolynDiscoveryDir . '/raw'),
    new RateLimiter(0),
);
$discoveredWolyn = $wolynDiscovery->listParishes();
ok(count($discoveredWolyn) === 2, 'Wołyń provider discovers available parishes from content page.');

// Metadata parser from the actual diagnostic Geneteka shape.
$stuff = '<img src="images/i.png" title="Uwagi: Rodzice ojca: Ilja i Tacijanna Tiliszczak. &#013;Data urodzenia: 14.08.1913 r. "><a href="http://www.przemysl.ap.gov.pl/" target="_blank"><img src="images/z.png" title="Miejsce przechowywania ksiąg"></a><a target="_blank" href="https://www.genealodzy.pl/user.php?op=userinfo&amp;uname=karpecki.lukasz"><img src="images/a.png"></a><a target="doc" href="https://www.szukajwarchiwach.gov.pl/jednostka/-/jednostka/18065584"><img src="images/s.png"></a><a href="fix.php?gid=12309516&amp;bdm=B&amp;w=06mp&amp;rid=16033"><img src="images/fix.png"></a>';
$meta = (new GenetekaMetadataParser())->parse($stuff);
ok(($meta['gid'] ?? null) === '12309516', 'Geneteka parser extracts gid.');
ok(($meta['indexed_by'] ?? null) === 'karpecki.lukasz', 'Geneteka parser extracts indexer.');
ok(($meta['document_url'] ?? null) === 'https://www.szukajwarchiwow.gov.pl/jednostka/-/jednostka/18065584' || ($meta['document_url'] ?? null) === 'https://www.szukajwarchiwach.gov.pl/jednostka/-/jednostka/18065584', 'Geneteka parser extracts document URL.');

// Wołyń provider end-to-end with fake HTTP.
$wolynHttp = new FakeHttpClient(fn (string $url): HttpResponse => new HttpResponse(200, [], $html, $url));
$wolynDir = $tmp . '/wolyn';
$wolynWriter = new JsonlWriter($wolynDir . '/records.jsonl', false);
$wolyn = new WolynMetrykiProvider(
    $wolynHttp,
    new JsonCheckpointStore($wolynDir . '/state.json'),
    new RawResponseStore($wolynDir . '/raw'),
    new RateLimiter(0),
);
$stats = $wolyn->acquire('Szumsk', 1835, 1835, $wolynWriter);
$wolynWriter->close();
ok($stats->records === 4, 'Wołyń provider maps all four record types.');
ok($wolynHttp->calls === 1, 'Wołyń provider performs one request per requested year.');
$lines = file($wolynDir . '/records.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$birth = null;
foreach ($lines as $line) {
    $r = json_decode($line, true);
    if (($r['record_type'] ?? null) === 'birth') {
        $birth = $r;
    }
}
ok(($birth['fields']['child']['given_names_raw'] ?? null) === 'Joanna', 'Wołyń birth mapping keeps child name.');
ok(($birth['fields']['source_locator']['scan_url'] ?? null) === 'https://example.test/birth', 'Wołyń birth mapping keeps scan URL.');
ok(($birth['representation']['kind'] ?? null) === 'indexer_rendering', 'Wołyń marks indexed values as indexer rendering.');
ok(($birth['representation']['verbatim_from_provider'] ?? null) === true, 'Wołyń marks indexed values as verbatim from provider.');
ok(($birth['representation']['producer']['indexer_id'] ?? null) === 'DM', 'Wołyń representation keeps indexer identity.');
ok(($birth['representation']['original_document_wording_asserted'] ?? null) === false, 'Wołyń does not assert original-document wording.');

// Geneteka provider end-to-end with one page.
$genetekaRow = [
    '1913', '', 'Anna ', 'Tiliszczak', 'Michał', 'Melania', 'Dzjurbjel', 'Imbramowice', 'Imbramowice', $stuff,
];
$genBody = json_encode(['recordsTotal' => '1', 'recordsFiltered' => '1', 'data' => [$genetekaRow]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$genHttp = new FakeHttpClient(fn (string $url): HttpResponse => new HttpResponse(200, [], (string) $genBody, $url));
$genDir = $tmp . '/gen';
$genWriter = new JsonlWriter($genDir . '/records.jsonl', false);
$gen = new GenetekaProvider(
    $genHttp,
    new JsonCheckpointStore($genDir . '/state.json'),
    new RawResponseStore($genDir . '/raw'),
    new RateLimiter(0),
);
$genStats = $gen->acquire('06mp', '4812', 'Imbramowice', ['B'], $genWriter);
$genWriter->close();
ok($genStats->records === 1, 'Geneteka provider maps one birth record.');
ok($genHttp->calls === 1, 'Geneteka single-page acquisition performs one HTTP request.');
$genLine = json_decode((string) file_get_contents($genDir . '/records.jsonl'), true);
ok(($genLine['provider_record_id'] ?? null) === '12309516', 'Geneteka uses gid as provider_record_id when available.');
ok(($genLine['fields']['person']['mother_surname_raw'] ?? null) === 'Dzjurbjel', 'Geneteka person mapping keeps mother surname.');
ok(($genLine['representation']['kind'] ?? null) === 'indexer_rendering', 'Geneteka marks indexed values as indexer rendering.');
ok(($genLine['representation']['producer']['indexer_id'] ?? null) === 'karpecki.lukasz', 'Geneteka representation keeps indexer identity.');
ok(($genLine['representation']['original_document_wording_asserted'] ?? null) === false, 'Geneteka does not assert original-document wording.');

// Resume should not call HTTP again.
$genWriter2 = new JsonlWriter($genDir . '/records.jsonl', true);
$gen2 = new GenetekaProvider(
    $genHttp,
    new JsonCheckpointStore($genDir . '/state.json'),
    new RawResponseStore($genDir . '/raw'),
    new RateLimiter(0),
);
$gen2->acquire('06mp', '4812', 'Imbramowice', ['B'], $genWriter2);
$genWriter2->close();
ok($genHttp->calls === 1, 'Geneteka resume uses checkpoint and makes no extra request.');

// Cleanup is intentionally omitted on failure to allow inspection.
if ($failures === 0) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($tmp);
    echo "All tests passed.\n";
    exit(0);
}

echo "$failures test(s) failed. Temp: $tmp\n";
exit(1);
