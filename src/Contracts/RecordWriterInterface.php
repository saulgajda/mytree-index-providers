<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Contracts;

use MyTree\IndexProviders\Domain\ExternalIndexRecord;

interface RecordWriterInterface
{
    public function write(ExternalIndexRecord $record): void;

    public function close(): void;
}
