<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Domain;

final readonly class HttpResponse
{
    /** @param array<string,list<string>> $headers */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        public string $url,
    ) {
    }
}
