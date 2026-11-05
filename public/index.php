<?php

declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

/** @param array<string, mixed> $context */
return static fn (array $context): Kernel => new Kernel(
    is_string($context['APP_ENV'] ?? null) ? $context['APP_ENV'] : 'prod',
    (bool) ($context['APP_DEBUG'] ?? false),
);
