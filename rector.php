<?php


declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Exception\Configuration\InvalidConfigurationException;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

try {
    return RectorConfig::configure()
        ->withPaths([
            __DIR__ . '/src',
            __DIR__ . '/tests',
        ])
        ->withPhpVersion(PhpVersion::PHP_85)
        ->withPhpSets(php85: true)
        ->withPreparedSets(
            deadCode: true,
            typeDeclarations: true,
            privatization: true,
            earlyReturn: true
        )
        ->withSets([
            SetList::CODE_QUALITY,
            SetList::CODING_STYLE,
        ]);
} catch (InvalidConfigurationException $e) {

}