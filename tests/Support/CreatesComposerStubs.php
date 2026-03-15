<?php

declare(strict_types=1);

namespace Momo\Discovery\Tests\Support;

trait CreatesComposerStubs
{
    private function writeRealFile(string $path): void
    {
        file_put_contents(
            $path,
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

    private function writeExistingPsr4(string $path, array $map): void
    {
        $export = var_export($map, true);

        file_put_contents(
            $path,
            "<?php\n\n\$vendorDir = dirname(__DIR__);\n\$baseDir = dirname(\$vendorDir);\n\nreturn {$export};\n",
        );
    }
}
