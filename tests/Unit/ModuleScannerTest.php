<?php

/**
 * This file is part of Momo Framework.
 *
 * @copyright Vahe Sargsyan
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 * @link      https://github.com/momo-framework
 */

declare(strict_types=1);

namespace Momo\Discovery\Tests\Unit;

use Momo\Discovery\ModuleScanner;
use Momo\Discovery\Tests\Support\CreatesModules;
use Momo\Discovery\Tests\Support\InteractsWithFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleScanner::class)]
final class ModuleScannerTest extends TestCase
{
    use CreatesModules;
    use InteractsWithFilesystem;

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

    #[Test]
    public function returns_empty_when_modules_dir_does_not_exist(): void
    {
        self::assertSame([], new ModuleScanner($this->tmpDir)->scan());
    }

    #[Test]
    public function returns_empty_when_modules_dir_is_empty(): void
    {
        mkdir($this->tmpDir . '/modules', 0755, true);

        self::assertSame([], new ModuleScanner($this->tmpDir)->scan());
    }

    #[Test]
    public function ignores_files_in_modules_directory(): void
    {
        mkdir($this->tmpDir . '/modules', 0755, true);
        file_put_contents($this->tmpDir . '/modules/somefile.txt', 'hello');

        self::assertSame([], new ModuleScanner($this->tmpDir)->scan());
    }

    #[Test]
    public function skips_module_without_composer_json(): void
    {
        mkdir($this->tmpDir . '/modules/Shop', 0755, true);

        self::assertSame([], new ModuleScanner($this->tmpDir)->scan());
    }

    #[Test]
    public function skips_module_with_invalid_json(): void
    {
        mkdir($this->tmpDir . '/modules/Broken', 0755, true);
        file_put_contents($this->tmpDir . '/modules/Broken/composer.json', '{ not valid json }');

        self::assertSame([], new ModuleScanner($this->tmpDir)->scan());
    }

    #[Test]
    public function skips_module_with_no_autoload_section(): void
    {
        $this->createModule($this->tmpDir, 'Empty', [
            'name'  => 'momo-module/empty',
            'extra' => ['momo' => ['providers' => []]],
        ]);

        self::assertSame([], new ModuleScanner($this->tmpDir)->scan());
    }

    #[Test]
    public function skips_module_with_no_psr4_section(): void
    {
        $this->createModule($this->tmpDir, 'NoPsr4', [
            'name'     => 'momo-module/no-psr4',
            'autoload' => ['classmap' => ['src/']],
        ]);

        self::assertSame([], new ModuleScanner($this->tmpDir)->scan());
    }

    #[Test]
    public function skips_psr4_entry_with_non_string_namespace(): void
    {
        mkdir($this->tmpDir . '/modules/Weird', 0755, true);
        file_put_contents(
            $this->tmpDir . '/modules/Weird/composer.json',
            '{"name":"momo-module/weird","autoload":{"psr-4":["src/"]}}',
        );

        self::assertSame([], new ModuleScanner($this->tmpDir)->scan());
    }

    #[Test]
    public function skips_psr4_path_entry_that_is_not_a_string(): void
    {
        $this->createModule($this->tmpDir, 'BadPaths', [
            'name'     => 'momo-module/bad-paths',
            'autoload' => ['psr-4' => ['Momo\\Module\\BadPaths\\' => [null, false, 123]]],
        ]);

        self::assertArrayNotHasKey('Momo\\Module\\BadPaths\\', new ModuleScanner($this->tmpDir)->scan());
    }

    #[Test]
    public function returns_namespace_and_absolute_path_for_valid_module(): void
    {
        $this->createSimpleModule($this->tmpDir, 'Shop');

        $result = new ModuleScanner($this->tmpDir)->scan();

        self::assertArrayHasKey('Momo\\Module\\Shop\\', $result);
        self::assertSame($this->tmpDir . '/modules/Shop/src', $result['Momo\\Module\\Shop\\'][0]);
    }

    #[Test]
    public function strips_trailing_slash_from_relative_path(): void
    {
        $this->createSimpleModule($this->tmpDir, 'Billing');

        $result = new ModuleScanner($this->tmpDir)->scan();

        self::assertStringEndsNotWith('/', $result['Momo\\Module\\Billing\\'][0]);
    }

    #[Test]
    public function scans_multiple_modules(): void
    {
        $this->createSimpleModule($this->tmpDir, 'Shop');
        $this->createSimpleModule($this->tmpDir, 'Billing');

        $result = new ModuleScanner($this->tmpDir)->scan();

        self::assertArrayHasKey('Momo\\Module\\Shop\\', $result);
        self::assertArrayHasKey('Momo\\Module\\Billing\\', $result);
        self::assertCount(2, $result);
    }

    #[Test]
    public function handles_multiple_psr4_entries_in_one_module(): void
    {
        $this->createModule($this->tmpDir, 'Shop', [
            'name'     => 'momo-module/shop',
            'autoload' => [
                'psr-4' => [
                    'Momo\\Module\\Shop\\'        => 'src/',
                    'Momo\\Module\\Shop\\Tests\\' => 'tests/',
                ],
            ],
        ]);

        $result = new ModuleScanner($this->tmpDir)->scan();

        self::assertArrayHasKey('Momo\\Module\\Shop\\', $result);
        self::assertArrayHasKey('Momo\\Module\\Shop\\Tests\\', $result);
    }

    #[Test]
    public function handles_array_psr4_path_value(): void
    {
        $this->createModule($this->tmpDir, 'Shop', [
            'name'     => 'momo-module/shop',
            'autoload' => ['psr-4' => ['Momo\\Module\\Shop\\' => ['src/', 'lib/']]],
        ]);

        $result = new ModuleScanner($this->tmpDir)->scan();

        self::assertCount(2, $result['Momo\\Module\\Shop\\']);
        self::assertStringEndsWith('/modules/Shop/src', $result['Momo\\Module\\Shop\\'][0]);
        self::assertStringEndsWith('/modules/Shop/lib', $result['Momo\\Module\\Shop\\'][1]);
    }
}
