<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Support;

final class RateLimiter
{
    private ?float $lastRequestAt = null;

    public function __construct(private readonly int $minimumDelayMs = 2000)
    {
    }

    public function beforeRequest(): void
    {
        if ($this->lastRequestAt !== null && $this->minimumDelayMs > 0) {
            $elapsedMs = (microtime(true) - $this->lastRequestAt) * 1000;
            $remaining = $this->minimumDelayMs - $elapsedMs;
            if ($remaining > 0) {
                usleep((int) ceil($remaining * 1000));
            }
        }
        $this->lastRequestAt = microtime(true);
    }
}
