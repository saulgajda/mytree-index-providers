<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Provider;

use JsonSerializable;
use MyTree\IndexProviders\Domain\RecordType;
use MyTree\IndexProviders\Domain\YearRange;

final readonly class GenetekaRecordAvailability implements JsonSerializable
{
    /** @param list<YearRange> $yearRanges */
    public function __construct(
        public RecordType $recordType,
        public string $providerParishId,
        public array $yearRanges,
        public string $sourceUrl,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'record_type' => $this->recordType->value,
            'provider_parish_id' => $this->providerParishId,
            'year_ranges' => array_map(
                static fn (YearRange $range): array => $range->jsonSerialize(),
                $this->yearRanges,
            ),
            'source_url' => $this->sourceUrl,
        ];
    }
}
