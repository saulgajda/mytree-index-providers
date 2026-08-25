<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Contracts;

use MyTree\IndexProviders\Domain\HttpResponse;

interface HttpClientInterface
{
    /** @param array<string,string> $headers */
    public function get(string $url, array $headers = []): HttpResponse;
}
