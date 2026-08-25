<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Contracts;

interface ProgressReporterInterface
{
    public function info(string $message): void;

    public function warning(string $message): void;
}
