<?php

declare(strict_types=1);

namespace Momo\Discovery;

use DirectoryIterator;

/**
 * Scans the modules/ directory and collects PSR-4 autoload entries
 * from each module's composer.json metadata file.
 *
 * Local modules are NOT Composer packages — they share the project vendor/.
 * Their composer.json contains ONLY: name, type, autoload, extra.momo.providers.
 * No require section is allowed.
 */
final class ModuleScanner
{
    private const MODULES_DIR = 'modules';

    public function __construct(
        private readonly string $rootDir,
    ) {}

    /**
     * @return array<string, list<string>> namespace => [absolute path]
     */
    public function scan(): array
    {
        $modulesDir = $this->rootDir . '/' . self::MODULES_DIR;

        if (!is_dir($modulesDir)) {
            return [];
        }

        $additions = [];

        foreach (new DirectoryIterator($modulesDir) as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
                continue;
            }

            $result = $this->scanModule($entry->getPathname());

            foreach ($result as $namespace => $paths) {
                $additions[$namespace] = $paths;
            }
        }

        return $additions;
    }

    /**
     * @return array<string, list<string>>
     */
    private function scanModule(string $modulePath): array
    {
        $composerJson = $modulePath . '/composer.json';

        if (!file_exists($composerJson)) {
            return [];
        }

        $raw = file_get_contents($composerJson);

        if ($raw === false) {
            return [];
        }

        /** @var array{autoload?: array{'psr-4'?: array<string, string|list<string>>}}|null $data */
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return [];
        }

        $additions = [];

        foreach ($data['autoload']['psr-4'] ?? [] as $namespace => $relativePath) {
            $paths = is_array($relativePath) ? $relativePath : [$relativePath];

            $additions[$namespace] = array_map(
                static fn(string $p): string => $modulePath . '/' . rtrim($p, '/'),
                $paths
            );
        }

        return $additions;
    }
}
