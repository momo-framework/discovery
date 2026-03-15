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

    private string $realFile;

    protected function setUp(): void
    {
        $this->tmpDir     = sys_get_temp_dir() . '/momo-patcher-test-' . uniqid();
        $this->vendorDir  = $this->tmpDir . '/vendor';
        $this->composerDir = $this->vendorDir . '/composer';
        $this->psr4File   = $this->composerDir . '/autoload_psr4.php';
        $this->realFile   = $this->vendorDir . '/autoload_real.php';

        mkdir($this->composerDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // autoload_psr4.php — file creation
    // -------------------------------------------------------------------------

    #[Test]
    public function creates_psr4_file_when_it_does_not_exist(): void
    {
        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        self::assertFileExists($this->psr4File);
    }

    #[Test]
    public function generated_psr4_file_is_valid_php(): void
    {
        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $output = shell_exec('php -l ' . escapeshellarg($this->psr4File) . ' 2>&1');
        self::assertStringContainsString('No syntax errors', (string) $output);
    }

    #[Test]
    public function generated_psr4_file_returns_array(): void
    {
        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $result = require $this->psr4File;
        self::assertIsArray($result);
    }

    // -------------------------------------------------------------------------
    // autoload_psr4.php — namespace injection
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

        $this->writeExistingPsr4(['Momo\\Module\\Shop\\' => [$oldPath]]);

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
    // autoload_psr4.php — path portability
    // -------------------------------------------------------------------------

    #[Test]
    public function generated_psr4_file_contains_base_dir_variable(): void
    {
        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $content = file_get_contents($this->psr4File);
        self::assertStringContainsString('$baseDir', (string) $content);
    }

    #[Test]
    public function generated_psr4_file_does_not_contain_hardcoded_absolute_paths_for_base(): void
    {
        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $content = file_get_contents($this->psr4File);
        self::assertStringNotContainsString("'" . $this->tmpDir . "'", (string) $content);
    }

    // -------------------------------------------------------------------------
    // autoload_psr4.php — edge cases
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

    #[Test]
    public function handles_namespace_with_trailing_backslash(): void
    {
        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $result = require $this->psr4File;
        self::assertArrayHasKey('Momo\\Module\\Shop\\', $result);
    }

    // -------------------------------------------------------------------------
    // autoload_real.php — hook injection
    // -------------------------------------------------------------------------

    #[Test]
    public function does_not_touch_real_file_when_it_does_not_exist(): void
    {
        // No autoload_real.php created — patch() must not throw
        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        self::assertFileDoesNotExist($this->realFile);
    }

    #[Test]
    public function injects_add_psr4_calls_into_real_file(): void
    {
        $this->writeRealFile();

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $content = file_get_contents($this->realFile);

        // var_export() doubles backslashes: 'Momo\\Module\\Shop\\' in source → 'Momo\\\\Module\\\\Shop\\\\' in file
        self::assertStringContainsString('$loader->addPsr4(', (string) $content);
        self::assertStringContainsString(var_export('Momo\\Module\\Shop\\', true), (string) $content);
    }

    #[Test]
    public function injected_real_file_is_valid_php(): void
    {
        $this->writeRealFile();

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $output = shell_exec('php -l ' . escapeshellarg($this->realFile) . ' 2>&1');
        self::assertStringContainsString('No syntax errors', (string) $output);
    }

    #[Test]
    public function injects_hook_before_return_loader_statement(): void
    {
        $this->writeRealFile();

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $content = file_get_contents($this->realFile);

        $hookPos   = strpos((string) $content, 'addPsr4');
        $returnPos = strrpos((string) $content, 'return $loader;');

        self::assertNotFalse($hookPos);
        self::assertNotFalse($returnPos);
        self::assertLessThan($returnPos, $hookPos, 'Hook must appear before return $loader;');
    }

    #[Test]
    public function hook_uses_relative_dir_expression_for_project_paths(): void
    {
        $this->writeRealFile();

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $content = file_get_contents($this->realFile);

        // Should use __DIR__ . '/../...' not the absolute tmpDir path
        self::assertStringContainsString("__DIR__ . '/../", (string) $content);
        self::assertStringNotContainsString($this->tmpDir . '/modules', (string) $content);
    }

    #[Test]
    public function hook_uses_absolute_path_when_outside_project_root(): void
    {
        $this->writeRealFile();
        $outsidePath = '/tmp/outside-project/src';

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Outside\\Ns\\' => [$outsidePath]]);

        $content = file_get_contents($this->realFile);
        self::assertStringContainsString(var_export($outsidePath, true), (string) $content);
    }

    #[Test]
    public function re_patching_does_not_duplicate_hook_entries(): void
    {
        $this->writeRealFile();

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        $content = (string) file_get_contents($this->realFile);

        // var_export() doubles backslashes — count the escaped form as it appears in the file
        $count = substr_count($content, var_export('Momo\\Module\\Shop\\', true));
        self::assertSame(1, $count);
    }

    #[Test]
    public function re_patching_with_updated_namespace_replaces_old_hook(): void
    {
        $this->writeRealFile();

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);
        $patcher->patch(['Momo\\Module\\Billing\\' => [$this->tmpDir . '/modules/Billing/src']]);

        $content = (string) file_get_contents($this->realFile);

        // Second patch replaces first — only Billing should be present
        self::assertStringNotContainsString(var_export('Momo\\Module\\Shop\\', true), $content);
        self::assertStringContainsString(var_export('Momo\\Module\\Billing\\', true), $content);
    }

    #[Test]
    public function injects_multiple_namespaces_into_real_file(): void
    {
        $this->writeRealFile();

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch([
            'Momo\\Module\\Shop\\'    => [$this->tmpDir . '/modules/Shop/src'],
            'Momo\\Module\\Billing\\' => [$this->tmpDir . '/modules/Billing/src'],
        ]);

        $content = (string) file_get_contents($this->realFile);
        self::assertStringContainsString(var_export('Momo\\Module\\Shop\\', true), $content);
        self::assertStringContainsString(var_export('Momo\\Module\\Billing\\', true), $content);
    }

    #[Test]
    public function skips_real_file_hook_when_return_loader_not_found(): void
    {
        // Write a real file without the expected `return $loader;`
        file_put_contents($this->realFile, "<?php\n// no return statement\n");
        $original = file_get_contents($this->realFile);

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        // File should remain unchanged
        self::assertSame($original, file_get_contents($this->realFile));
    }

    #[Test]
    public function skips_hook_when_real_file_is_not_readable(): void
    {
        // Skip on Windows — chmod has no effect there.
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('chmod is not reliable on Windows.');
        }

        $this->writeRealFile();
        chmod($this->realFile, 0000);

        $patcher = new AutoloadPatcher($this->vendorDir);

        // Must not throw — silently skips the hook injection.
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        chmod($this->realFile, 0644);

        // autoload_psr4.php was still written — only the real-file hook was skipped.
        self::assertFileExists($this->psr4File);
    }

    #[Test]
    public function strip_previous_hook_returns_content_unchanged_when_return_loader_absent_after_marker(): void
    {
        // A file where the marker is present but `return $loader;` does not follow it.
        // stripPreviousHook() returns the content unchanged; hookRealFile() then bails
        // because `return $loader;` is absent from the full content too.
        $broken = "<?php\n        " . '// momo-discovery:patched' . "\n        \$loader->addPsr4('X\\\\', []);\n";
        file_put_contents($this->realFile, $broken);

        $original = file_get_contents($this->realFile);

        $patcher = new AutoloadPatcher($this->vendorDir);
        $patcher->patch(['Momo\\Module\\Shop\\' => [$this->tmpDir . '/modules/Shop/src']]);

        self::assertSame($original, file_get_contents($this->realFile));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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

    /**
     * Write a minimal autoload_real.php that mimics Composer's generated file.
     */
    private function writeRealFile(): void
    {
        file_put_contents(
            $this->realFile,
            <<<'PHP'
            <?php

            class ComposerAutoloaderInitAbc123
            {
                private static $loader;

                public static function getLoader()
                {
                    if (null !== self::$loader) {
                        return self::$loader;
                    }

                    $loader = new \Composer\Autoload\ClassLoader();
                    $loader->register(true);

                    return $loader;
                }
            }
            PHP,
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
