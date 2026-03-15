<?php

declare(strict_types=1);

namespace Momo\Discovery\Tests\Unit;

use Momo\Discovery\ModuleAutoloadWriter;
use Momo\Discovery\Tests\Support\InteractsWithFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleAutoloadWriter::class)]
final class ModuleAutoloadWriterTest extends TestCase
{
    use InteractsWithFilesystem;

    private string $tmpDir;

    private string $cacheFile;

    protected function setUp(): void
    {
        $this->tmpDir    = sys_get_temp_dir() . '/momo-writer-test-' . uniqid();
        $this->cacheFile = $this->tmpDir . '/bootstrap/cache/modules-autoload.php';
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // File creation
    // -------------------------------------------------------------------------

    #[Test]
    public function creates_cache_directory_when_it_does_not_exist(): void
    {
        $writer = new ModuleAutoloadWriter($this->tmpDir);
        $writer->write($this->cacheFile, [
            'Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src'],
        ]);

        self::assertFileExists($this->cacheFile);
    }

    #[Test]
    public function generated_file_is_valid_php(): void
    {
        $writer = new ModuleAutoloadWriter($this->tmpDir);
        $writer->write($this->cacheFile, [
            'Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src'],
        ]);

        $output = shell_exec('php -l ' . escapeshellarg($this->cacheFile) . ' 2>&1');
        self::assertStringContainsString('No syntax errors', (string) $output);
    }

    // -------------------------------------------------------------------------
    // Content — addPsr4 calls
    // -------------------------------------------------------------------------

    #[Test]
    public function generated_file_contains_add_psr4_call(): void
    {
        $writer = new ModuleAutoloadWriter($this->tmpDir);
        $writer->write($this->cacheFile, [
            'Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src'],
        ]);

        $content = (string) file_get_contents($this->cacheFile);
        self::assertStringContainsString('$loader->addPsr4(', $content);
        self::assertStringContainsString(var_export('Momo\\Module\\Shop\\', true), $content);
    }

    #[Test]
    public function generated_file_contains_add_psr4_calls_for_multiple_namespaces(): void
    {
        $writer = new ModuleAutoloadWriter($this->tmpDir);
        $writer->write($this->cacheFile, [
            'Momo\\Module\\Shop\\'    => [$this->tmpDir . '/modules/Shop/src'],
            'Momo\\Module\\Billing\\' => [$this->tmpDir . '/modules/Billing/src'],
        ]);

        $content = (string) file_get_contents($this->cacheFile);
        self::assertStringContainsString(var_export('Momo\\Module\\Shop\\', true), $content);
        self::assertStringContainsString(var_export('Momo\\Module\\Billing\\', true), $content);
    }

    // -------------------------------------------------------------------------
    // Path portability
    // -------------------------------------------------------------------------

    #[Test]
    public function uses_relative_dir_expression_for_paths_inside_project_root(): void
    {
        $writer = new ModuleAutoloadWriter($this->tmpDir);
        $writer->write($this->cacheFile, [
            'Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src'],
        ]);

        $content = (string) file_get_contents($this->cacheFile);

        // Path inside project root must be expressed relative to __DIR__ (bootstrap/cache/)
        self::assertStringContainsString("__DIR__ . '/../../modules/Shop/src'", $content);
        self::assertStringNotContainsString($this->tmpDir . '/modules', $content);
    }

    #[Test]
    public function uses_absolute_path_for_paths_outside_project_root(): void
    {
        $outsidePath = '/tmp/outside-project/src';

        $writer = new ModuleAutoloadWriter($this->tmpDir);
        $writer->write($this->cacheFile, [
            'Outside\\Ns\\' => [$outsidePath],
        ]);

        $content = (string) file_get_contents($this->cacheFile);
        self::assertStringContainsString(var_export($outsidePath, true), $content);
    }

    #[Test]
    public function handles_multiple_paths_for_single_namespace(): void
    {
        $writer = new ModuleAutoloadWriter($this->tmpDir);
        $writer->write($this->cacheFile, [
            'Momo\\Module\\Shop\\' => [
                $this->tmpDir . '/modules/Shop/src',
                $this->tmpDir . '/modules/Shop/lib',
            ],
        ]);

        $content = (string) file_get_contents($this->cacheFile);
        self::assertStringContainsString("__DIR__ . '/../../modules/Shop/src'", $content);
        self::assertStringContainsString("__DIR__ . '/../../modules/Shop/lib'", $content);
    }

    // -------------------------------------------------------------------------
    // Idempotency
    // -------------------------------------------------------------------------

    #[Test]
    public function overwriting_produces_identical_output(): void
    {
        $additions = ['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']];

        $writer = new ModuleAutoloadWriter($this->tmpDir);
        $writer->write($this->cacheFile, $additions);

        $first = file_get_contents($this->cacheFile);

        $writer->write($this->cacheFile, $additions);
        $second = file_get_contents($this->cacheFile);

        self::assertSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    #[Test]
    public function throws_when_cache_directory_cannot_be_created(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('chmod is not reliable on Windows.');
        }

        // Make tmpDir read-only so mkdir inside it fails
        chmod($this->tmpDir, 0444);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/could not be created/');

        $writer = new ModuleAutoloadWriter($this->tmpDir);
        $writer->write($this->cacheFile, [
            'Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src'],
        ]);

        chmod($this->tmpDir, 0755);
    }

    #[Test]
    public function throws_when_cache_file_cannot_be_written(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('chmod is not reliable on Windows.');
        }

        // Create the cache dir but make it read-only
        $cacheDir = dirname($this->cacheFile);
        mkdir($cacheDir, 0755, true);
        chmod($cacheDir, 0444);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to write/');

        $writer = new ModuleAutoloadWriter($this->tmpDir);
        $writer->write($this->cacheFile, [
            'Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src'],
        ]);

        chmod($cacheDir, 0755);
    }
}
