<?php

declare(strict_types=1);

namespace Momo\Discovery\Tests\Unit;

use Momo\Discovery\AutoloadPatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AutoloadPatcher::class)]
final class AutoloadPatcherTest extends TestCase
{
    private string $tmpDir;

    private string $vendorDir;

    private string $composerDir;

    private string $psr4File;

    protected function setUp(): void
    {
        $this->tmpDir     = sys_get_temp_dir() . '/momo-patcher-test-' . uniqid();
        $this->vendorDir  = $this->tmpDir . '/vendor';
        $this->composerDir = $this->vendorDir . '/composer';
        $this->psr4File   = $this->composerDir . '/autoload_psr4.php';

        mkdir($this->composerDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // File creation
    // -------------------------------------------------------------------------

    #[Test]
    public function creates_psr4_file_when_it_does_not_exist(): void
    {
        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        self::assertFileExists($this->psr4File);
    }

    #[Test]
    public function generated_file_is_valid_php(): void
    {
        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $output = shell_exec('php -l ' . escapeshellarg($this->psr4File) . ' 2>&1');
        self::assertStringContainsString('No syntax errors', (string) $output);
    }

    #[Test]
    public function generated_file_returns_array(): void
    {
        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $result = require $this->psr4File;
        self::assertIsArray($result);
    }

    // -------------------------------------------------------------------------
    // Namespace injection
    // -------------------------------------------------------------------------

    #[Test]
    public function injects_namespace_into_empty_file(): void
    {
        $this->writeExistingPsr4([]);

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $result = require $this->psr4File;
        self::assertArrayHasKey('Momo\\Module\\Shop\\', $result);
    }

    #[Test]
    public function merges_with_existing_namespaces(): void
    {
        $this->writeExistingPsr4([
            'Symfony\\Component\\Console\\' => [$this->vendorDir . '/symfony/console/src'],
        ]);

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $result = require $this->psr4File;

        self::assertArrayHasKey('Symfony\\Component\\Console\\', $result);
        self::assertArrayHasKey('Momo\\Module\\Shop\\', $result);
    }

    #[Test]
    public function local_module_overwrites_existing_same_namespace(): void
    {
        $oldPath = $this->vendorDir . '/old/path';
        $newPath = $this->tmpDir . '/modules/Shop/src';

        $this->writeExistingPsr4([
            'Momo\\Module\\Shop\\' => [$oldPath],
        ]);

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$newPath]]);

        $result = require $this->psr4File;

        self::assertSame([$newPath], $result['Momo\\Module\\Shop\\']);
    }

    #[Test]
    public function injects_multiple_namespaces(): void
    {
        $this->writeExistingPsr4([]);

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch([
            'Momo\\Module\\Shop\\'    => [$this->tmpDir . '/modules/Shop/src'],
            'Momo\\Module\\Billing\\' => [$this->tmpDir . '/modules/Billing/src'],
        ]);

        $result = require $this->psr4File;

        self::assertArrayHasKey('Momo\\Module\\Shop\\', $result);
        self::assertArrayHasKey('Momo\\Module\\Billing\\', $result);
        self::assertCount(2, $result);
    }

    // -------------------------------------------------------------------------
    // Path portability
    // -------------------------------------------------------------------------

    #[Test]
    public function generated_file_contains_base_dir_variable(): void
    {
        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $content = file_get_contents($this->psr4File);
        self::assertStringContainsString('$baseDir', (string) $content);
    }

    #[Test]
    public function generated_file_does_not_contain_hardcoded_absolute_paths_for_base(): void
    {
        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $content = file_get_contents($this->psr4File);

        // The tmpDir absolute path should be replaced with $baseDir expression
        self::assertStringNotContainsString("'" . $this->tmpDir . "'", (string) $content);
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    #[Test]
    public function patch_with_empty_additions_writes_existing_unchanged(): void
    {
        $existing = ['Symfony\\Component\\Console\\' => [$this->vendorDir . '/symfony/console/src']];
        $this->writeExistingPsr4($existing);

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch([]);

        $result = require $this->psr4File;
        self::assertArrayHasKey('Symfony\\Component\\Console\\', $result);
    }

    #[Test]
    public function skips_existing_file_that_does_not_return_array(): void
    {
        file_put_contents($this->psr4File, "<?php\nreturn 'not-an-array';\n");

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $result = require $this->psr4File;

        self::assertArrayHasKey('Momo\\Module\\Shop\\', $result);
        self::assertCount(1, $result);
    }

    #[Test]
    public function skips_entry_with_non_string_namespace_key(): void
    {
        file_put_contents(
            $this->psr4File,
            "<?php\nreturn [0 => ['/some/path'], 'Valid\\\\Ns\\\\' => ['/valid/path']];\n",
        );

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch([]);

        $result = require $this->psr4File;

        self::assertArrayHasKey('Valid\\Ns\\', $result);
        self::assertArrayNotHasKey(0, $result);
    }

    #[Test]
    public function handles_namespace_with_trailing_backslash(): void
    {
        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $result = require $this->psr4File;

        self::assertArrayHasKey('Momo\\Module\\Shop\\', $result);
    }


    #[Test]
    public function skips_entries_with_non_string_namespace_when_reading_existing(): void
    {
        file_put_contents(
            $this->psr4File,
            "<?php\nreturn ['Valid\\\\Ns\\\\' => ['/some/path'], 'Bad\\\\Ns\\\\' => '/not-an-array'];\n",
        );

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch([]);

        $result = require $this->psr4File;

        self::assertArrayHasKey('Valid\\Ns\\', $result);
        self::assertArrayNotHasKey('Bad\\Ns\\', $result);
    }

    /**
     * @param array<string, list<string>> $map
     */
    private function writeExistingPsr4(array $map): void
    {
        $export = var_export($map, true);
        file_put_contents(
            $this->psr4File,
            "<?php\n\n\$vendorDir = dirname(__DIR__);\n\$baseDir = dirname(\$vendorDir);\n\nreturn {$export};\n",
        );
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }

}
