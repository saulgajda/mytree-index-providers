<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

abstract class TestCase extends PhpUnitTestCase
{
    protected string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir() . '/mytree-index-providers-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0775, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->tmp, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($this->tmp);
        }

        parent::tearDown();
    }

    protected function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__ . '/fixtures/' . $name);
        self::assertNotFalse($contents, "Fixture $name could not be read.");

        return $contents;
    }
}
