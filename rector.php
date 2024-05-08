<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php70\Rector\FuncCall\RandomFunctionRector;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Php74\Rector\LNumber\AddLiteralSeparatorToNumberRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/config',
    ])
    ->withSkip([
        JsonThrowOnErrorRector::class,
        AddLiteralSeparatorToNumberRector::class,
        // 暂不需要安全随机数
        RandomFunctionRector::class
    ])
    ->withPhpSets()
    ->withPHPStanConfigs([
        __DIR__ . '/phpstan.neon',
    ]);
