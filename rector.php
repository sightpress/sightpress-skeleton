<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/src', __DIR__.'/config', __DIR__.'/public', __DIR__.'/tests', __DIR__.'/bin/console'])
    ->withSkip([__DIR__.'/config/reference.php'])
    ->withSets([LevelSetList::UP_TO_PHP_84])
    ->withCache(__DIR__.'/var/rector');
