<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Domain;

use InvalidArgumentException;
use JsonSerializable;

final readonly class YearRange implements JsonSerializable
{
    public function __construct(
        public int $from,
        public int $to,
    ) {
        if ($from < 1 || $from > 9999 || $to < 1 || $to > 9999) {
            throw new InvalidArgumentException('Year range values must be between 1 and 9999.');
        }
        if ($from > $to) {
            throw new InvalidArgumentException('Year range start cannot be greater than its end.');
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
