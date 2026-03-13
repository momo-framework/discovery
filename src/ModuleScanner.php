<?php

declare(strict_types=1);

namespace Momo\Discovery;

use DirectoryIterator;

/**
 * Scans 'modules/' for PSR-4 metadata.
 * * CONSTRAINT: Local modules share the root vendor/.
 * They MUST NOT contain a 'require' section in their composer.json.
 */
final readonly class ModuleScanner
{
    private const string MODULES_DIR = 'modules';

    public function __construct(
        private string $rootDir,
    ) {}

    /**
     * Start the scanning process.
     *
     * Iterates through the modules directory, finds valid module folders,
     * and aggregates their PSR-4 namespace mappings.
     *
     * @return array<string, list<string>> Map of [Namespace\ => [Absolute/Path]]
     */
    public function scan(): array
    {
        $modulesDir = $this->rootDir . '/' . self::MODULES_DIR;

        if (!is_dir($modulesDir)) {
            return [];
        }

        $additions = [];

        foreach (new DirectoryIterator($modulesDir) as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            if (!$entry->isDir()) {
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
     * Extract autoload metadata from a single module.
     *
     * Reads the module's composer.json and resolves relative PSR-4 paths
     * into absolute system paths based on the module's location.
     *
     * @param string $modulePath Full path to the module directory.
     * @return array<string, list<string>>
     * @codeCoverageIgnore
     */
    private function scanModule(string $modulePath): array
    {
        $composerJson = $modulePath . '/composer.json';

        if (!file_exists($composerJson)) {
            return [];
        }

        $raw = file_get_contents($composerJson);

        if ($raw === false) {
            return []; // @codeCoverageIgnore
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return [];
        }

        $autoload = $data['autoload'] ?? null;

        if (!is_array($autoload)) {
            return [];
        }

        $psr4 = $autoload['psr-4'] ?? null;

        if (!is_array($psr4)) {
            return [];
        }

        $additions = [];

        foreach ($psr4 as $namespace => $relativePath) {
            if (!is_string($namespace)) {
                continue;
            }

            $paths = is_array($relativePath) ? $relativePath : [$relativePath];

            $resolved = [];
            foreach ($paths as $p) {
                if (is_string($p)) {
                    $resolved[] = $modulePath . '/' . rtrim($p, '/');
                }
            }

            if ($resolved !== []) {
                $additions[$namespace] = $resolved;
            }
        }

        return $additions;
    }
}
