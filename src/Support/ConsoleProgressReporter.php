<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Support;

use MyTree\IndexProviders\Contracts\ProgressReporterInterface;

final class ConsoleProgressReporter implements ProgressReporterInterface
{
    public function info(string $message): void
    {
        fwrite(STDERR, '[INFO] ' . $message . PHP_EOL);
    }

    public function warning(string $message): void
    {
        fwrite(STDERR, '[WARN] ' . $message . PHP_EOL);
    }
}
