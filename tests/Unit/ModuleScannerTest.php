<?php

declare(strict_types=1);

namespace Momo\Discovery\Tests\Unit;

use Momo\Discovery\ModuleScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleScanner::class)]
final class ModuleScannerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/momo-discovery-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // No modules dir
    // -------------------------------------------------------------------------

    #[Test]
    public function returns_empty_when_modules_dir_does_not_exist(): void
    {
        $scanner = new ModuleScanner($this->tmpDir);

        self::assertSame([], $scanner->scan());
    }

    // -------------------------------------------------------------------------
    // Empty modules dir
    // -------------------------------------------------------------------------

    #[Test]
    public function returns_empty_when_modules_dir_is_empty(): void
    {
        mkdir($this->tmpDir . '/modules', 0755, true);

        $scanner = new ModuleScanner($this->tmpDir);

        self::assertSame([], $scanner->scan());
    }

    // -------------------------------------------------------------------------
    // Module without composer.json
    // -------------------------------------------------------------------------

    #[Test]
    public function skips_module_without_composer_json(): void
    {
        mkdir($this->tmpDir . '/modules/Shop', 0755, true);

        $scanner = new ModuleScanner($this->tmpDir);

        self::assertSame([], $scanner->scan());
    }

    // -------------------------------------------------------------------------
    // Module with valid composer.json
    // -------------------------------------------------------------------------

    #[Test]
    public function returns_namespace_from_module_composer_json(): void
    {
        $this->createModule('Shop', [
            'name' => 'momo-module/shop',
            'autoload' => [
                'psr-4' => ['Momo\\Module\\Shop\\' => 'src/'],
            ],
        ]);

        $scanner = new ModuleScanner($this->tmpDir);
        $result  = $scanner->scan();

        self::assertArrayHasKey('Momo\\Module\\Shop\\', $result);
        self::assertStringEndsWith('/modules/Shop/src', $result['Momo\\Module\\Shop\\'][0]);
    }

    #[Test]
    public function strips_trailing_slash_from_relative_path(): void
    {
        $this->createModule('Billing', [
            'name' => 'momo-module/billing',
            'autoload' => [
                'psr-4' => ['Momo\\Module\\Billing\\' => 'src/'],
            ],
        ]);

        $scanner = new ModuleScanner($this->tmpDir);
        $result  = $scanner->scan();

        self::assertStringEndsNotWith('/', $result['Momo\\Module\\Billing\\'][0]);
    }

    #[Test]
    public function returns_absolute_path_for_namespace(): void
    {
        $this->createModule('Shop', [
            'name' => 'momo-module/shop',
            'autoload' => [
                'psr-4' => ['Momo\\Module\\Shop\\' => 'src/'],
            ],
        ]);

        $scanner = new ModuleScanner($this->tmpDir);
        $result  = $scanner->scan();

        $expectedPath = $this->tmpDir . '/modules/Shop/src';
        self::assertSame($expectedPath, $result['Momo\\Module\\Shop\\'][0]);
    }

    // -------------------------------------------------------------------------
    // Multiple modules
    // -------------------------------------------------------------------------

    #[Test]
    public function scans_multiple_modules(): void
    {
        $this->createModule('Shop', [
            'name' => 'momo-module/shop',
            'autoload' => ['psr-4' => ['Momo\\Module\\Shop\\' => 'src/']],
        ]);

        $this->createModule('Billing', [
            'name' => 'momo-module/billing',
            'autoload' => ['psr-4' => ['Momo\\Module\\Billing\\' => 'src/']],
        ]);

        $scanner = new ModuleScanner($this->tmpDir);
        $result  = $scanner->scan();

        self::assertArrayHasKey('Momo\\Module\\Shop\\', $result);
        self::assertArrayHasKey('Momo\\Module\\Billing\\', $result);
        self::assertCount(2, $result);
    }

    // -------------------------------------------------------------------------
    // Multiple namespaces in one module
    // -------------------------------------------------------------------------

    #[Test]
    public function handles_multiple_psr4_entries_in_one_module(): void
    {
        $this->createModule('Shop', [
            'name' => 'momo-module/shop',
            'autoload' => [
                'psr-4' => [
                    'Momo\\Module\\Shop\\'  => 'src/',
                    'Momo\\Module\\Shop\\Tests\\' => 'tests/',
                ],
            ],
        ]);

        $scanner = new ModuleScanner($this->tmpDir);
        $result  = $scanner->scan();

        self::assertArrayHasKey('Momo\\Module\\Shop\\', $result);
        self::assertArrayHasKey('Momo\\Module\\Shop\\Tests\\', $result);
    }

    // -------------------------------------------------------------------------
    // Module with no autoload section
    // -------------------------------------------------------------------------

    #[Test]
    public function skips_module_with_no_autoload_section(): void
    {
        $this->createModule('Empty', [
            'name' => 'momo-module/empty',
            'extra' => ['momo' => ['providers' => []]],
        ]);

        $scanner = new ModuleScanner($this->tmpDir);

        self::assertSame([], $scanner->scan());
    }

    // -------------------------------------------------------------------------
    // Invalid composer.json
    // -------------------------------------------------------------------------

    #[Test]
    public function skips_module_with_invalid_json(): void
    {
        mkdir($this->tmpDir . '/modules/Broken', 0755, true);
        file_put_contents(
            $this->tmpDir . '/modules/Broken/composer.json',
            '{ this is not valid json }'
        );

        $scanner = new ModuleScanner($this->tmpDir);

        self::assertSame([], $scanner->scan());
    }

    // -------------------------------------------------------------------------
    // Files in modules dir (not directories) are ignored
    // -------------------------------------------------------------------------

    #[Test]
    public function ignores_files_in_modules_directory(): void
    {
        mkdir($this->tmpDir . '/modules', 0755, true);
        file_put_contents($this->tmpDir . '/modules/somefile.txt', 'hello');

        $scanner = new ModuleScanner($this->tmpDir);

        self::assertSame([], $scanner->scan());
    }

    // -------------------------------------------------------------------------
    // Array psr-4 paths (multiple paths per namespace)
    // -------------------------------------------------------------------------

    #[Test]
    public function handles_array_psr4_path_value(): void
    {
        $this->createModule('Shop', [
            'name' => 'momo-module/shop',
            'autoload' => [
                'psr-4' => [
                    'Momo\\Module\\Shop\\' => ['src/', 'lib/'],
                ],
            ],
        ]);

        $scanner = new ModuleScanner($this->tmpDir);
        $result  = $scanner->scan();

        self::assertCount(2, $result['Momo\\Module\\Shop\\']);
        self::assertStringEndsWith('/modules/Shop/src', $result['Momo\\Module\\Shop\\'][0]);
        self::assertStringEndsWith('/modules/Shop/lib', $result['Momo\\Module\\Shop\\'][1]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createModule(string $name, array $composerData): void
    {
        $moduleDir = $this->tmpDir . '/modules/' . $name;
        mkdir($moduleDir, 0755, true);

        file_put_contents(
            $moduleDir . '/composer.json',
            json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
