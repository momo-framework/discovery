<?php

declare(strict_types=1);

namespace Momo\Discovery;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

final class MomoPlugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io): void {}

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_AUTOLOAD_DUMP => 'onPostAutoloadDump',
        ];
    }

    public function onPostAutoloadDump(Event $event): void
    {
        $composer  = $event->getComposer();
        $io        = $event->getIO();
        $rawVendorDir = $composer->getConfig()->get('vendor-dir');

        if (!is_string($rawVendorDir)) {
            $io->writeError('<warning>momo-discovery:</warning> could not determine vendor-dir.');
            return;
        }

        $vendorDir = rtrim($rawVendorDir, '/');
        $rootDir   = dirname($vendorDir);

        $scanner   = new ModuleScanner($rootDir);
        $additions = $scanner->scan();

        if ($additions === []) {
            $io->write('<info>momo-discovery:</info> no local modules found.');
            return;
        }

        $patcher = new AutoloadPatcher($vendorDir);
        $patcher->patch($additions);

        $io->write(
            sprintf(
                '<info>momo-discovery:</info> injected %d local module namespace(s): %s',
                count($additions),
                implode(', ', array_keys($additions)),
            ),
        );
    }
}
