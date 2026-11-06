<?php

declare(strict_types=1);

use App\Tests\PluginsEnabledKernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

// The integration build runs the suite twice: every official plugin enabled,
// then every plugin disabled (PLAN §3, CLAUDE.md "Commands").
$plugins = $_SERVER['SIGHTPRESS_TEST_PLUGINS'] ?? getenv('SIGHTPRESS_TEST_PLUGINS') ?: 'disabled';
if (!in_array($plugins, ['enabled', 'disabled'], true)) {
    fwrite(\STDERR, sprintf("SIGHTPRESS_TEST_PLUGINS must be \"enabled\" or \"disabled\", not %s.\n", json_encode($plugins)));
    exit(2);
}
$_SERVER['SIGHTPRESS_TEST_PLUGINS'] = $_ENV['SIGHTPRESS_TEST_PLUGINS'] = $plugins;
if ('enabled' === $plugins) {
    $_SERVER['KERNEL_CLASS'] = $_ENV['KERNEL_CLASS'] = PluginsEnabledKernel::class;
}
