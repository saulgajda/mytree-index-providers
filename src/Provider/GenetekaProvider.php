<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Provider;

use MyTree\IndexProviders\Contracts\CheckpointStoreInterface;
use MyTree\IndexProviders\Contracts\HttpClientInterface;
use MyTree\IndexProviders\Contracts\ProgressReporterInterface;
use MyTree\IndexProviders\Contracts\RecordWriterInterface;
use MyTree\IndexProviders\Domain\AcquisitionStats;
use MyTree\IndexProviders\Domain\AvailableParish;
use MyTree\IndexProviders\Domain\ExternalIndexRecord;
use MyTree\IndexProviders\Domain\RecordType;
use MyTree\IndexProviders\Domain\ValueRepresentation;
use MyTree\IndexProviders\Storage\RawResponseStore;
use MyTree\IndexProviders\Support\HtmlSelectParser;
use MyTree\IndexProviders\Support\NullProgressReporter;
use MyTree\IndexProviders\Support\RateLimiter;
use RuntimeException;

final class GenetekaProvider
{
    /** @var array<string,true> */
    private array $warnedParishMismatches = [];

    private const ENDPOINT = 'https://geneteka.genealodzy.pl/api/getAct.php';
    private const INDEX_ENDPOINT = 'https://geneteka.genealodzy.pl/index.php';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly CheckpointStoreInterface $checkpoints,
        private readonly RawResponseStore $rawStore,
        private readonly RateLimiter $rateLimiter = new RateLimiter(2000),
        private readonly GenetekaMetadataParser $metadataParser = new GenetekaMetadataParser(),
        private readonly HtmlSelectParser $selectParser = new HtmlSelectParser(),
        private readonly ProgressReporterInterface $progress = new NullProgressReporter(),
    ) {
    }

    /**
     * Lists Geneteka regions exposed by the live search form.
     *
     * @return list<array{code:string,name:string}>
     */
    public function listRegions(bool $force = false): array
    {
        [$html] = $this->fetchIndexPage(null, $force);
        $options = $this->selectParser->options($html, 'w');
        $regions = [];
        foreach ($options as $option) {
            $code = trim($option['value']);
            $name = trim($option['label']);
            if ($code === '' || $name === '' || !preg_match('~^[0-9]{2}[A-Za-z]{2,3}$~', $code)) {
                continue;
            }
            $regions[] = ['code' => $code, 'name' => $name];
        }
        if ($regions === []) {
            throw new RuntimeException('Could not discover Geneteka regions from the search form.');
        }
        return $regions;
    }

    /** @return list<AvailableParish> */
    public function listParishes(string $region, bool $force = false): array
    {
        $region = trim($region);
        if ($region === '') {
            throw new RuntimeException('Geneteka region cannot be empty.');
        }

        [$html, $url] = $this->fetchIndexPage($region, $force);
        $regionName = null;
        foreach ($this->selectParser->options($html, 'w') as $option) {
            if ($option['value'] === $region) {
                $regionName = $option['label'];
                break;
            }
        }

        $items = [];
        foreach ($this->selectParser->options($html, 'rid') as $option) {
            $id = trim($option['value']);
            $name = trim($option['label']);
            if ($id === '' || $name === '' || !ctype_digit($id)) {
                continue;
            }
            $items[] = new AvailableParish(
                provider: 'geneteka',
                name: $name,
                providerParishId: $id,
                regionCode: $region,
                regionName: $regionName,
                metadata: ['source_url' => $url, 'discovery_strategy' => 'search_form_select'],
            );
        }

        if ($items === []) {
            $items = $this->listParishesFromApiFallback($region, $regionName, $force);
        }
        if ($items === []) {
            throw new RuntimeException("Could not discover Geneteka parishes for region $region.");
        }

        $byId = [];
        foreach ($items as $item) {
            $byId[$item->providerParishId ?? $item->name] = $item;
        }
        $items = array_values($byId);
        usort($items, static fn (AvailableParish $a, AvailableParish $b): int => strnatcasecmp($a->name, $b->name));
        $this->progress->info("Geneteka $region: discovered " . count($items) . ' parishes.');
        return $items;
    }

    /** @return list<AvailableParish> */
    public function listAllParishes(bool $force = false): array
    {
        $result = [];
        foreach ($this->listRegions($force) as $region) {
            foreach ($this->listParishes($region['code'], $force) as $parish) {
                $result[] = $parish;
            }
        }
        return $result;
    }

    /** @return array{0:string,1:string} */
    private function fetchIndexPage(?string $region, bool $force): array
    {
        $params = ['op' => 'gt', 'lang' => 'pol'];
        if ($region !== null) {
            $params['w'] = $region;
            $params['rid'] = 'A';
        }
        $url = self::INDEX_ENDPOINT . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $cacheKey = $region === null ? 'parish-discovery_regions' : 'parish-discovery_' . $region;
        $cached = !$force ? $this->rawStore->get('geneteka', $cacheKey, 'html') : null;
        if ($cached !== null) {
            return [$cached, $url];
        }

        $this->rateLimiter->beforeRequest();
        $response = $this->http->get($url, [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Referer' => self::INDEX_ENDPOINT,
        ]);
        if ($response->status < 200 || $response->status >= 300) {
            throw new RuntimeException("Geneteka HTTP {$response->status}: $url");
        }
        $this->rawStore->put('geneteka', $cacheKey, 'html', $response->body, [
            'provider' => 'geneteka',
            'purpose' => 'parish_discovery',
            'requested_url' => $url,
            'http_status' => $response->status,
            'retrieved_at' => gmdate(DATE_ATOM),
            'region' => $region,
        ]);
        return [$response->body, $url];
    }

    /** @return list<AvailableParish> */
    private function listParishesFromApiFallback(string $region, ?string $regionName, bool $force): array
    {
        $url = self::ENDPOINT . '?' . http_build_query([
            'bdm' => $this->providerTypeCode(RecordType::Birth), 'w' => $region, 'rid' => 'A', 'length' => 1, 'start' => 0,
        ], '', '&', PHP_QUERY_RFC3986);
        $cacheKey = 'parish-discovery-api_' . $region;
        $body = !$force ? $this->rawStore->get('geneteka', $cacheKey, 'json') : null;
        if ($body === null) {
            $this->rateLimiter->beforeRequest();
            $response = $this->http->get($url, [
                'Referer' => self::INDEX_ENDPOINT,
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json,text/javascript,*/*;q=0.1',
            ]);
            if ($response->status < 200 || $response->status >= 300) {
                return [];
            }
            $body = $response->body;
            $this->rawStore->put('geneteka', $cacheKey, 'json', $body, [
                'provider' => 'geneteka', 'purpose' => 'parish_discovery_api_fallback',
                'requested_url' => $url, 'http_status' => $response->status,
                'retrieved_at' => gmdate(DATE_ATOM), 'region' => $region,
            ]);
        }

        $decoded = json_decode($body, true);
        $raw = is_array($decoded) ? ($decoded['parishes'] ?? null) : null;
        if (!is_array($raw)) {
            return [];
        }

        $pairs = [];
        foreach ($raw as $key => $value) {
            $id = null;
            $name = null;
            if (is_string($key) && ctype_digit($key) && is_scalar($value)) {
                $id = $key; $name = trim((string) $value);
            } elseif (is_array($value)) {
                $idCandidate = $value['rid'] ?? $value['id'] ?? $value['value'] ?? ($value[0] ?? null);
                $nameCandidate = $value['parish'] ?? $value['name'] ?? $value['label'] ?? $value['text'] ?? ($value[1] ?? null);
                if (is_scalar($idCandidate) && is_scalar($nameCandidate)) {
                    $id = trim((string) $idCandidate); $name = trim((string) $nameCandidate);
                    if (!ctype_digit($id) && ctype_digit($name)) {
                        [$id, $name] = [$name, $id];
                    }
                }
            }
            if ($id !== null && $name !== null && ctype_digit($id) && $name !== '') {
                $pairs[$id] = $name;
            }
        }

        $result = [];
        foreach ($pairs as $id => $name) {
            $result[] = new AvailableParish(
                provider: 'geneteka', name: $name, providerParishId: (string) $id,
                regionCode: $region, regionName: $regionName,
                metadata: ['source_url' => $url, 'discovery_strategy' => 'getAct_parishes_fallback'],
            );
        }
        return $result;
    }

    /** @param list<RecordType> $types */
    public function acquire(
        string $region,
        string $parishId,
        ?string $parishName,
        array $types,
        RecordWriterInterface $writer,
        int $pageSize = 50,
        bool $force = false,
    ): AcquisitionStats {
        foreach ($types as $type) {
            if (!$type instanceof RecordType) {
                throw new RuntimeException('Geneteka record types must be RecordType enum values.');
            }
            $this->providerTypeCode($type);
        }

        $stats = new AcquisitionStats();
        foreach ($types as $type) {
            $this->acquireType($region, $parishId, $parishName, $type, $writer, $pageSize, $force, $stats);
        }
        return $stats;
    }

    private function acquireType(
        string $region,
        string $parishId,
        ?string $parishName,
        RecordType $type,
        RecordWriterInterface $writer,
        int $pageSize,
        bool $force,
        AcquisitionStats $stats,
    ): void {
        $metaKey = "geneteka:$region:$parishId:{$type->value}:meta";
        $meta = $force ? null : $this->checkpoints->get($metaKey);
        $total = is_array($meta) && isset($meta['records_total']) ? (int) $meta['records_total'] : null;
        $firstPageConsumed = false;

        if ($total === null) {
            $first = $this->fetchPage($region, $parishId, $type, 0, $pageSize, $stats, !$force);
            $total = (int) ($first['recordsTotal'] ?? 0);
            $this->checkpoints->set($metaKey, [
                'records_total' => $total,
                'page_size' => $pageSize,
                'updated_at' => gmdate(DATE_ATOM),
            ]);
            $this->progress->info("Geneteka $region/$parishId {$type->value}: $total records.");
            $this->consumePage($first, $region, $parishId, $parishName, $type, 0, $pageSize, $writer, $stats, $force);
            $firstPageConsumed = true;
        }

        $pages = $total === 0 ? 0 : (int) ceil($total / $pageSize);
        for ($page = $firstPageConsumed ? 1 : 0; $page < $pages; $page++) {
            $checkpointKey = "geneteka:$region:$parishId:{$type->value}:page:$page";
            if (!$force && $this->checkpoints->get($checkpointKey) === true) {
                $stats->skippedUnits++;
                continue;
            }
            if ($page === 0 && $this->checkpoints->get($checkpointKey) === true && !$force) {
                continue;
            }
            $json = $this->fetchPage($region, $parishId, $type, $page, $pageSize, $stats, !$force);
            $this->consumePage($json, $region, $parishId, $parishName, $type, $page, $pageSize, $writer, $stats, $force);
        }
    }

    /** @return array<string,mixed> */
    private function fetchPage(string $region, string $parishId, RecordType $type, int $page, int $pageSize, AcquisitionStats $stats, bool $useCache): array
    {
        $providerType = $this->providerTypeCode($type);
        $start = $page * $pageSize;
        $url = self::ENDPOINT . '?' . http_build_query([
            'bdm' => $providerType,
            'w' => $region,
            'rid' => $parishId,
            'length' => $pageSize,
            'start' => $start,
        ], '', '&', PHP_QUERY_RFC3986);

        $cacheKey = "{$region}_{$parishId}_{$providerType}_{$start}";
        $cachedBody = $useCache ? $this->rawStore->get('geneteka', $cacheKey, 'json') : null;
        $cacheMeta = $useCache ? $this->rawStore->metadata('geneteka', $cacheKey, 'json') : null;
        if ($cachedBody !== null) {
            $this->progress->info("Geneteka $region/$parishId {$type->value}: using cached page start=$start.");
            $body = $cachedBody;
            $retrievedAt = is_array($cacheMeta) && isset($cacheMeta['retrieved_at']) ? (string) $cacheMeta['retrieved_at'] : gmdate(DATE_ATOM);
            $rawPath = $this->rawStore->path('geneteka', $cacheKey, 'json');
            $rawSha256 = hash('sha256', $body);
        } else {
            $this->progress->info("Geneteka $region/$parishId {$type->value}: page " . ($page + 1) . " (start=$start).");
            $this->rateLimiter->beforeRequest();
            $response = $this->http->get($url, [
                'Referer' => 'https://geneteka.genealodzy.pl/index.php',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json,text/javascript,*/*;q=0.1',
            ]);
            $stats->requests++;
            if ($response->status < 200 || $response->status >= 300) {
                throw new RuntimeException("Geneteka HTTP {$response->status}: $url");
            }
            $body = $response->body;
            $retrievedAt = gmdate(DATE_ATOM);
            $rawSha256 = hash('sha256', $body);
            $rawPath = $this->rawStore->put('geneteka', $cacheKey, 'json', $body, [
                'provider' => 'geneteka',
                'requested_url' => $url,
                'http_status' => $response->status,
                'retrieved_at' => $retrievedAt,
                'region' => $region,
                'parish_id' => $parishId,
                'record_type' => $type->value,
                'start' => $start,
                'length' => $pageSize,
            ]);
            $stats->cacheWrites++;
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
            throw new RuntimeException('Geneteka returned unexpected JSON for ' . $url);
        }
        $decoded['_mytree_raw_path'] = $rawPath;
        $decoded['_mytree_request_url'] = $url;
        $decoded['_mytree_retrieved_at'] = $retrievedAt;
        $decoded['_mytree_raw_sha256'] = $rawSha256;
        return $decoded;
    }

    /** @param array<string,mixed> $json */
    private function consumePage(
        array $json,
        string $region,
        string $parishId,
        ?string $parishName,
        RecordType $type,
        int $page,
        int $pageSize,
        RecordWriterInterface $writer,
        AcquisitionStats $stats,
        bool $force,
    ): void {
        $checkpointKey = "geneteka:$region:$parishId:{$type->value}:page:$page";
        if (!$force && $this->checkpoints->get($checkpointKey) === true) {
            return;
        }

        foreach ($json['data'] as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }
            $writer->write($this->mapRecord(
                $row,
                $region,
                $parishId,
                $parishName,
                $type,
                $page,
                (int) $rowIndex,
                (string) ($json['_mytree_request_url'] ?? ''),
                (string) ($json['_mytree_raw_path'] ?? ''),
                (string) ($json['_mytree_raw_sha256'] ?? ''),
                (string) ($json['_mytree_retrieved_at'] ?? gmdate(DATE_ATOM)),
            ));
            $stats->record($type->value);
        }

        $this->checkpoints->set($checkpointKey, true);
    }

    /** @param array<int,mixed> $row */
    private function mapRecord(
        array $row,
        string $region,
        string $parishId,
        ?string $parishName,
        RecordType $type,
        int $page,
        int $rowIndex,
        string $requestUrl,
        string $rawPath,
        string $rawSha256,
        string $retrievedAt,
    ): ExternalIndexRecord {
        $providerType = $this->providerTypeCode($type);
        $values = array_map(static fn (mixed $v): string => trim((string) $v), array_values($row));
        if (count($values) < 10) {
            throw new RuntimeException('Geneteka row has fewer than 10 columns.');
        }
        $metadata = $this->metadataParser->parse($values[9]);
        $providerRecordId = isset($metadata['gid']) && $metadata['gid'] !== ''
            ? (string) $metadata['gid']
            : hash('sha256', 'geneteka|' . $region . '|' . $parishId . '|' . $providerType . '|page:' . $page . '|row:' . $rowIndex . '|' . json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($type === RecordType::Marriage) {
            $fields = [
                'year_raw' => $values[0],
                'record_number_raw' => $values[1],
                'groom' => [
                    'given_names_raw' => $values[2],
                    'surname_raw' => $this->visibleText($values[3]),
                    'parents_raw' => $values[4],
                ],
                'bride' => [
                    'given_names_raw' => $values[5],
                    'surname_raw' => $this->visibleText($values[6]),
                    'parents_raw' => $values[7],
                ],
                'parish_raw' => $values[8],
                'metadata' => $metadata,
            ];
        } else {
            $fields = [
                'year_raw' => $values[0],
                'record_number_raw' => $values[1],
                'person' => [
                    'given_names_raw' => $values[2],
                    'surname_raw' => $this->visibleText($values[3]),
                    'father_given_names_raw' => $values[4],
                    'mother_given_names_raw' => $values[5],
                    'mother_surname_raw' => $this->visibleText($values[6]),
                ],
                'parish_raw' => $values[7],
                'place_raw' => $values[8],
                'metadata' => $metadata,
            ];
        }

        $year = preg_match('~^\d{4}$~', $values[0]) ? (int) $values[0] : null;
        $parish = $type === RecordType::Marriage ? ($values[8] !== '' ? $values[8] : $parishName) : ($values[7] !== '' ? $values[7] : $parishName);
        if ($parishName !== null && $parish !== null && strcasecmp(trim($parish), trim($parishName)) !== 0) {
            $warningKey = strtolower(trim($parishName)) . '|' . strtolower(trim($parish));
            if (!isset($this->warnedParishMismatches[$warningKey])) {
                $this->warnedParishMismatches[$warningKey] = true;
                $this->progress->warning("Geneteka parish mismatch: requested '$parishName', row says '$parish'. Verify rid=$parishId.");
            }
        }

        return new ExternalIndexRecord(
            provider: 'geneteka',
            providerRecordId: $providerRecordId,
            recordType: $type->value,
            parish: $parish,
            year: $year,
            fields: $fields,
            raw: [
                'record_type_code' => $providerType,
                'columns' => $values,
            ],
            provenance: [
                'provider' => 'geneteka',
                'region' => $region,
                'parish_id' => $parishId,
                'requested_parish_name' => $parishName,
                'page' => $page,
                'row_index' => $rowIndex,
                'request_url' => $requestUrl,
                'retrieved_at' => $retrievedAt,
                'raw_response_path' => $rawPath,
                'raw_response_sha256' => $rawSha256,
                'provider_record_gid' => $metadata['gid'] ?? null,
                'id_strategy' => isset($metadata['gid']) ? 'geneteka_gid' : 'page_row_content_fallback',
            ],
            representation: ValueRepresentation::indexerRendering(
                provider: 'geneteka',
                indexedBy: isset($metadata['indexed_by']) ? (string) $metadata['indexed_by'] : null,
            ),
        );
    }

    private function visibleText(string $value): string
    {
        $beforeTag = explode('<', $value, 2)[0];
        return trim(html_entity_decode($beforeTag, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function providerTypeCode(RecordType $type): string
    {
        return match ($type) {
            RecordType::Birth => 'B',
            RecordType::Marriage => 'S',
            RecordType::Death => 'D',
            default => throw new RuntimeException('Unsupported Geneteka record type: ' . $type->value),
        };
    }
}
