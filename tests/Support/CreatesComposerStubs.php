<?php

declare(strict_types=1);

namespace Momo\Discovery\Tests\Support;

trait CreatesComposerStubs
{
    /**
     * @param array<string, list<string>> $map
     */
    private function writeExistingPsr4(string $path, array $map): void
    {
        $export = var_export($map, true);

        file_put_contents(
            $path,
            "<?php\n\n\$vendorDir = dirname(__DIR__);\n\$baseDir = dirname(\$vendorDir);\n\nreturn {$export};\n",
        );
    }
}
