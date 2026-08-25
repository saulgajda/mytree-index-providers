<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Domain;

final class AcquisitionStats
{
    public int $requests = 0;
    public int $records = 0;
    public int $cacheWrites = 0;
    public int $skippedUnits = 0;

    /** @var array<string,int> */
    public array $recordsByType = [];

    public function record(string $type): void
    {
        $this->records++;
        $this->recordsByType[$type] = ($this->recordsByType[$type] ?? 0) + 1;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'requests' => $this->requests,
            'records' => $this->records,
            'records_by_type' => $this->recordsByType,
            'cache_writes' => $this->cacheWrites,
            'skipped_units' => $this->skippedUnits,
        ];
    }
}
