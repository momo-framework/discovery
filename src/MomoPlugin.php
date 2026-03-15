<?php

/**
 * Part of Momo Framework.
 *
 * © Momo Framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Unauthorized copying, modification, or distribution of this file,
 * via any medium, is strictly prohibited without prior written permission
 * from the copyright holder.
 *
 * @author    Vahe Sargsyan <w33bvGL>
 * @copyright Momo Framework
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 * @link      https://github.com/momo-framework
 */

declare(strict_types=1);

namespace Momo\Discovery;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * @codeCoverageIgnore
 */
final class MomoPlugin implements EventSubscriberInterface, PluginInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_AUTOLOAD_DUMP => 'onPostAutoloadDump',
        ];
    }

    public function activate(Composer $composer, IOInterface $io): void {}

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    public function onPostAutoloadDump(Event $event): void
    {
        $composer = $event->getComposer();
        $io = $event->getIO();
        $rawVendorDir = $composer->getConfig()->get('vendor-dir');

        if (! is_string($rawVendorDir)) {
            $io->writeError('<warning>momo-discovery:</warning> could not determine vendor-dir.');

            return;
        }

        $vendorDir = mb_rtrim($rawVendorDir, '/');
        $rootDir = dirname($vendorDir);

        $scanner = new ModuleScanner($rootDir);
        $additions = $scanner->scan();

        $cacheFile = $rootDir . '/bootstrap/cache/modules-autoload.php';

        if ($additions === []) {
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }

            $io->write('<info>momo-discovery:</info> no local modules found.');

            return;
        }

        $writer = new ModuleAutoloadWriter($rootDir);
        $writer->write($cacheFile, $additions);

        $io->write(
            sprintf(
                '<info>momo-discovery:</info> injected %d local module namespace(s): %s',
                count($additions),
                implode(', ', array_keys($additions)),
            ),
        );
    }
}
