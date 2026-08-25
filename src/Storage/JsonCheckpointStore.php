<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Storage;

use MyTree\IndexProviders\Contracts\CheckpointStoreInterface;
use RuntimeException;

final class JsonCheckpointStore implements CheckpointStoreInterface
{
    /** @var array<string,mixed> */
    private array $data = [];

    public function __construct(private readonly string $path)
    {
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $this->data = $decoded;
            }
        }
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->flush();
    }

    public function delete(string $key): void
    {
        unset($this->data[$key]);
        $this->flush();
    }

    private function flush(): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create checkpoint directory: ' . $dir);
        }
        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $tmp = $this->path . '.tmp';
        file_put_contents($tmp, $json . PHP_EOL, LOCK_EX);
        rename($tmp, $this->path);
    }
}
