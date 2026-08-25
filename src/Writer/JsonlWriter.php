<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Writer;

use MyTree\IndexProviders\Contracts\RecordWriterInterface;
use MyTree\IndexProviders\Domain\ExternalIndexRecord;
use RuntimeException;

final class JsonlWriter implements RecordWriterInterface
{
    /** @var resource|null */
    private $handle;

    /** @var array<string,true> */
    private array $seen = [];

    private int $written = 0;
    private int $duplicates = 0;

    public function __construct(private readonly string $path, bool $append = true, bool $deduplicate = true)
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create output directory: ' . $dir);
        }
        if ($append && $deduplicate && is_file($path)) {
            $this->loadExistingIds($path);
        }
        $this->handle = fopen($path, $append ? 'ab' : 'wb');
        if ($this->handle === false) {
            throw new RuntimeException('Cannot open JSONL output: ' . $path);
        }
    }

    public function write(ExternalIndexRecord $record): void
    {
        if (!is_resource($this->handle)) {
            throw new RuntimeException('Writer already closed.');
        }
        if (isset($this->seen[$record->providerRecordId])) {
            $this->duplicates++;
            return;
        }
        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        fwrite($this->handle, $line . PHP_EOL);
        fflush($this->handle);
        $this->seen[$record->providerRecordId] = true;
        $this->written++;
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
        $this->handle = null;
    }

    public function writtenCount(): int
    {
        return $this->written;
    }

    public function duplicateCount(): int
    {
        return $this->duplicates;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function loadExistingIds(string $path): void
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return;
        }
        while (($line = fgets($fh)) !== false) {
            $decoded = json_decode($line, true);
            if (is_array($decoded) && isset($decoded['provider_record_id']) && is_string($decoded['provider_record_id'])) {
                $this->seen[$decoded['provider_record_id']] = true;
            }
        }
        fclose($fh);
    }
}
