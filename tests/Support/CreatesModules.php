<?php

declare(strict_types=1);

namespace Momo\Discovery\Tests\Support;

trait CreatesModules
{
    private function createModule(string $rootDir, string $name, array $composerData): void
    {
        $moduleDir = $rootDir . '/modules/' . $name;
        mkdir($moduleDir, 0755, true);

        file_put_contents(
            $moduleDir . '/composer.json',
            json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    private function createSimpleModule(string $rootDir, string $name, array $psr4 = []): void
    {
        if ($psr4 === []) {
            $psr4 = ['Momo\\Module\\' . $name . '\\' => 'src/'];
        }

        $this->createModule($rootDir, $name, [
            'name'     => 'momo-module/' . strtolower($name),
            'autoload' => ['psr-4' => $psr4],
        ]);
    }
}
