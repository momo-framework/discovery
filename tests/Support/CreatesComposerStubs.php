<?php

/**
 * This file is part of Momo Framework.
 *
 * @copyright Vahe Sargsyan
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 * @link      https://github.com/momo-framework
 */

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
