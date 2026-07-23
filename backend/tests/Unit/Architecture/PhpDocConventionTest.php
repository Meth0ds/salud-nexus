<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Enforce the professional PHPDoc format used throughout the Laravel backend.
 */
final class PhpDocConventionTest extends TestCase
{
    public function test_backend_phpdoc_is_multiline_descriptive_and_punctuated(): void
    {
        foreach ($this->phpDocFiles() as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);

            preg_match_all('#/\*\*[\s\S]*?\*/#', $source, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$phpDoc, $offset]) {
                self::assertIsString($phpDoc);
                self::assertIsInt($offset);
                $location = $path.':'.$this->lineNumber($source, $offset);

                self::assertStringContainsString(
                    "\n",
                    $phpDoc,
                    $location.' must use a multiline PHPDoc block.',
                );

                $description = $this->description($phpDoc);

                self::assertNotSame(
                    '',
                    $description,
                    $location.' must include an English description before its annotations.',
                );
                self::assertStringEndsWith(
                    '.',
                    $description,
                    $location.' PHPDoc description must end with a period.',
                );
            }
        }
    }

    /**
     * Discover PHP files that belong to the maintained backend source tree.
     *
     * @return iterable<string>
     */
    private function phpDocFiles(): iterable
    {
        $roots = [
            app_path(),
            base_path('config'),
            base_path('database'),
            base_path('public'),
            base_path('routes'),
            base_path('tests'),
        ];

        foreach ($roots as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            /**
             * Preserve the recursive iterator value type for static analysis.
             *
             * @var SplFileInfo $file
             */
            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    yield $file->getPathname();
                }
            }
        }
    }

    /**
     * Extract the prose that appears before the first PHPDoc annotation.
     */
    private function description(string $phpDoc): string
    {
        $body = substr($phpDoc, 3, -2);
        $lines = preg_split('/\R/', $body);
        self::assertIsArray($lines);
        $description = [];

        foreach ($lines as $line) {
            $normalized = trim((string) preg_replace('/^\s*\*\s?/', '', $line));

            if ($normalized === '') {
                continue;
            }

            if (str_starts_with($normalized, '@')) {
                break;
            }

            $description[] = $normalized;
        }

        return implode(' ', $description);
    }

    /**
     * Calculate the one-based source line for a byte offset.
     */
    private function lineNumber(string $source, int $offset): int
    {
        return substr_count(substr($source, 0, $offset), "\n") + 1;
    }
}
