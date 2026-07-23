<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Illuminate\Database\Eloquent\Model;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Verify framework isolation and strict Eloquent behavior.
 */
final class DomainIsolationTest extends TestCase
{
    public function test_domain_code_has_no_laravel_or_illuminate_dependencies(): void
    {
        $domainDirectories = [app_path('Shared/Domain')];

        foreach (glob(app_path('Modules/*/Domain'), GLOB_ONLYDIR) ?: [] as $moduleDomain) {
            $domainDirectories[] = $moduleDomain;
        }

        foreach ($domainDirectories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            /**
             * Preserve the recursive iterator value type for static analysis.
             *
             * @var SplFileInfo $file
             */
            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = file_get_contents($file->getPathname());
                self::assertIsString($source);
                self::assertStringNotContainsString('Illuminate\\', $source, $file->getPathname());
                self::assertStringNotContainsString('Laravel\\', $source, $file->getPathname());
            }
        }
    }

    public function test_eloquent_strictness_is_enabled_outside_production(): void
    {
        self::assertTrue(Model::preventsLazyLoading());
        self::assertTrue(Model::preventsSilentlyDiscardingAttributes());
        self::assertTrue(Model::preventsAccessingMissingAttributes());
    }
}
