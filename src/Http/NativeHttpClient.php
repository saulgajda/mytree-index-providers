<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Http;

use MyTree\IndexProviders\Contracts\HttpClientInterface;
use MyTree\IndexProviders\Domain\HttpResponse;
use RuntimeException;

final class NativeHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly int $timeoutSeconds = 60,
        private readonly int $maxRetries = 3,
        private readonly string $userAgent = 'MyTree-Index-Providers/0.1 (personal genealogy research)',
    ) {
    }

    public function get(string $url, array $headers = []): HttpResponse
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;
            try {
                $response = $this->requestOnce($url, $headers);
                if ($response->status !== 429 && $response->status < 500) {
                    return $response;
                }

                if ($attempt >= $this->maxRetries) {
                    return $response;
                }

                $retryAfter = $response->headers['retry-after'][0] ?? null;
                $sleep = is_numeric($retryAfter) ? max(1, (int) $retryAfter) : $attempt * 2;
                sleep($sleep);
            } catch (RuntimeException $e) {
                $lastError = $e;
                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }
                sleep($attempt * 2);
            }
        }

        throw $lastError ?? new RuntimeException('HTTP request failed.');
    }

    /** @param array<string,string> $headers */
    private function requestOnce(string $url, array $headers): HttpResponse
    {
        $headerLines = [
            'User-Agent: ' . $this->userAgent,
            'Accept: */*',
            'Connection: close',
        ];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headerLines),
                'timeout' => $this->timeoutSeconds,
                'ignore_errors' => true,
                'follow_location' => 1,
                'max_redirects' => 5,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];

        if ($body === false && $responseHeaders === []) {
            $error = error_get_last();
            throw new RuntimeException('HTTP GET failed for ' . $url . ': ' . ($error['message'] ?? 'unknown error'));
        }

        [$status, $parsedHeaders] = $this->parseHeaders($responseHeaders);

        return new HttpResponse($status, $parsedHeaders, $body === false ? '' : $body, $url);
    }

    /**
     * @param list<string> $lines
     * @return array{0:int,1:array<string,list<string>>}
     */
    private function parseHeaders(array $lines): array
    {
        $status = 0;
        $headers = [];
        foreach ($lines as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $line, $m)) {
                $status = (int) $m[1];
                $headers = [];
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            $headers[$name] ??= [];
            $headers[$name][] = $value;
        }

        return [$status, $headers];
    }
}
