<?php

declare(strict_types=1);

namespace Momo\Discovery;

use DirectoryIterator;

final readonly class ModuleScanner
{
    private const string MODULES_DIR = 'modules';

    public function __construct(
        private string $rootDir,
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public function scan(): array
    {
        $modulesDir = $this->rootDir . '/' . self::MODULES_DIR;

        if (!is_dir($modulesDir)) {
            return [];
        }

        /** @var array<string, list<string>> $additions */
        $additions = [];

        foreach (new DirectoryIterator($modulesDir) as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            if (!$entry->isDir()) {
                continue;
            }

            foreach ($this->scanModule($entry->getPathname()) as $namespace => $paths) {
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

        /** @var array<string, list<string>> $additions */
        $additions = [];

        foreach ($psr4 as $namespace => $relativePath) {
            if (!is_string($namespace)) {
                continue;
            }

            $paths = is_array($relativePath) ? $relativePath : [$relativePath];

            /** @var list<string> $resolved */
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
