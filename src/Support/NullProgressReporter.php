<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Support;

use MyTree\IndexProviders\Contracts\ProgressReporterInterface;

final class NullProgressReporter implements ProgressReporterInterface
{
    public function info(string $message): void {}
    public function warning(string $message): void {}
}
