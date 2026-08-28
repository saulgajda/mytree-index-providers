<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Tests\Support;

use Closure;
use MyTree\IndexProviders\Contracts\HttpClientInterface;
use MyTree\IndexProviders\Domain\HttpResponse;

final class FakeHttpClient implements HttpClientInterface
{
    public int $calls = 0;

    /** @var list<string> */
    public array $urls = [];

    private Closure $responder;

    /** @param callable(string):HttpResponse $responder */
    public function __construct(callable $responder)
    {
        $this->responder = Closure::fromCallable($responder);
    }

    public function get(string $url, array $headers = []): HttpResponse
    {
        $this->calls++;
        $this->urls[] = $url;

        return ($this->responder)($url);
    }
}
