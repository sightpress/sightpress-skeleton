<?php

declare(strict_types=1);

namespace App\Tests;

use Composer\InstalledVersions;
use SightPress\Core\Kernel\CoreKernel;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * Test-only: the app with the bundle of every installed plugin registered,
 * for the integration build's "every official plugin enabled" run
 * (SIGHTPRESS_TEST_PLUGINS=enabled). It stands in for the core's
 * PluginBundleLoader and per-site activation, which arrive in M6. App\Kernel
 * is final and empty, so this extends the same core kernel.
 */
final class PluginsEnabledKernel extends CoreKernel
{
    /** @return list<class-string<BundleInterface>> the bundle class of every installed sightpress-plugin package */
    public static function pluginBundles(): array
    {
        $bundles = [];
        foreach (InstalledVersions::getInstalledPackagesByType('sightpress-plugin') as $package) {
            $path = InstalledVersions::getInstallPath($package);
            $manifest = null === $path ? null : json_decode((string) file_get_contents($path.'/composer.json'), true);
            $bundle = \is_array($manifest) && \is_array($manifest['extra'] ?? null) && \is_array($manifest['extra']['sightpress'] ?? null) ? ($manifest['extra']['sightpress']['bundle'] ?? null) : null;
            if (\is_string($bundle) && is_a($bundle, BundleInterface::class, true)) {
                $bundles[] = $bundle;
            }
        }
        sort($bundles);

        return array_values(array_unique($bundles));
    }

    public function registerBundles(): iterable
    {
        yield from parent::registerBundles();
        foreach (self::pluginBundles() as $bundle) {
            yield new $bundle();
        }
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir().'-plugins';
    }
}
