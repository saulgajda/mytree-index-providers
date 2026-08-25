<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Storage;

use RuntimeException;

final class RawResponseStore
{
    public function __construct(private readonly string $root)
    {
    }

    public function get(string $provider, string $key, string $extension): ?string
    {
        $path = $this->path($provider, $key, $extension);
        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    /** @return array<string,mixed>|null */
    public function metadata(string $provider, string $key, string $extension): ?array
    {
        $path = $this->path($provider, $key, $extension) . '.meta.json';
        if (!is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string,mixed> $metadata */
    public function put(string $provider, string $key, string $extension, string $body, array $metadata = []): string
    {
        $path = $this->path($provider, $key, $extension);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create raw response directory: ' . $dir);
        }

        file_put_contents($path, $body, LOCK_EX);
        $metadata += [
            'sha256' => hash('sha256', $body),
            'bytes' => strlen($body),
        ];
        file_put_contents(
            $path . '.meta.json',
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
            LOCK_EX,
        );

        return $path;
    }

    public function path(string $provider, string $key, string $extension): string
    {
        $safeKey = preg_replace('~[^A-Za-z0-9._-]+~', '_', $key) ?: sha1($key);
        return rtrim($this->root, '/') . '/' . $provider . '/' . $safeKey . '.' . ltrim($extension, '.');
    }
}
