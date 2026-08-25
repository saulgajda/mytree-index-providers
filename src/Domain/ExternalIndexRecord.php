<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Domain;

use JsonSerializable;

final readonly class ExternalIndexRecord implements JsonSerializable
{
    /**
     * @param array<string,mixed> $fields Structured but source-faithful fields.
     * @param array<string,mixed> $raw Raw source values/cells retained without semantic interpretation.
     * @param array<string,mixed> $provenance Acquisition and locator metadata.
     */
    public function __construct(
        public string $provider,
        public string $providerRecordId,
        public string $recordType,
        public ?string $parish,
        public ?int $year,
        public array $fields,
        public array $raw,
        public array $provenance,
    ) {
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'schema' => 'mytree.external-index-record.v1',
            'provider' => $this->provider,
            'provider_record_id' => $this->providerRecordId,
            'record_type' => $this->recordType,
            'parish' => $this->parish,
            'year' => $this->year,
            'fields' => $this->fields,
            'raw' => $this->raw,
            'provenance' => $this->provenance,
        ];
    }
}
