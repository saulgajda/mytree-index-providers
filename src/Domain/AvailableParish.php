<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Domain;

final readonly class AvailableParish
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $provider,
        public string $name,
        public ?string $providerParishId = null,
        public ?string $regionCode = null,
        public ?string $regionName = null,
        public array $metadata = [],
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'schema' => 'mytree.available-parish.v1',
            'provider' => $this->provider,
            'provider_parish_id' => $this->providerParishId,
            'name' => $this->name,
            'region_code' => $this->regionCode,
            'region_name' => $this->regionName,
            'metadata' => $this->metadata,
        ];
    }
}
