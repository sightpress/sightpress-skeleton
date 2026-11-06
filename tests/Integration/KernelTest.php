<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Kernel;
use App\Tests\PluginsEnabledKernel;
use Composer\InstalledVersions;
use SightPress\Core\SightPressCoreBundle;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\DependencyInjection\Kernel\BundleInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\BundleAdapter;
use Symfony\Component\HttpKernel\KernelInterface;

final class KernelTest extends KernelTestCase
{
    public function testTheKernelExtendsTheCoreKernelAndRegistersEveryCoreBundleOnce(): void
    {
        $kernel = self::bootKernel();

        self::assertInstanceOf('enabled' === $_SERVER['SIGHTPRESS_TEST_PLUGINS'] ? PluginsEnabledKernel::class : Kernel::class, $kernel);
        self::assertSame(\dirname(__DIR__, 2), $kernel->getProjectDir());

        // Symfony wraps the bundles it registers itself (ServicesBundle, ConsoleBundle) in an adapter.
        $bundles = array_map(
            static fn (BundleInterface $bundle): string => ($bundle instanceof BundleAdapter ? $bundle->getInnerBundle() : $bundle)::class,
            $kernel->getBundles(),
        );
        foreach (SightPressCoreBundle::requiredBundles() as $required) {
            self::assertContains($required, $bundles);
        }
        self::assertCount(\count(array_unique($bundles)), $bundles);
    }

    /** The official plugin bundles, once sightpress:plugin:install or composer.dev.json has installed them. */
    private const array OFFICIAL_PLUGIN_BUNDLES = [
        'SightPress\Plugin\AiHelper\AiHelperPlugin',
        'SightPress\Plugin\Articles\ArticlesPlugin',
        'SightPress\Plugin\CommunityHelper\CommunityHelperPlugin',
        'SightPress\Plugin\FileImporter\FileImporterPlugin',
        'SightPress\Plugin\FormBuilder\FormBuilderPlugin',
        'SightPress\Plugin\ImageFinder\ImageFinderPlugin',
        'SightPress\Plugin\NewsletterManager\NewsletterManagerPlugin',
        'SightPress\Plugin\NotificationsHelper\NotificationsHelperPlugin',
    ];

    /** @return list<string> */
    private static function pluginBundlesOf(KernelInterface $kernel): array
    {
        $plugins = [];
        foreach ($kernel->getBundles() as $bundle) {
            $class = ($bundle instanceof BundleAdapter ? $bundle->getInnerBundle() : $bundle)::class;
            if (str_starts_with($class, 'SightPress\\Plugin\\')) {
                $plugins[] = $class;
            }
        }
        sort($plugins);

        return $plugins;
    }

    public function testWithEveryPluginDisabledNoPluginBundleIsLoaded(): void
    {
        if ('disabled' !== $_SERVER['SIGHTPRESS_TEST_PLUGINS']) {
            self::markTestSkipped('Runs in the "every plugin disabled" build (SIGHTPRESS_TEST_PLUGINS=disabled).');
        }

        self::assertSame([], self::pluginBundlesOf(self::bootKernel()));
    }

    public function testWithEveryPluginEnabledEachInstalledPluginIsLoadedAndTheAppStillWorks(): void
    {
        if ('enabled' !== $_SERVER['SIGHTPRESS_TEST_PLUGINS']) {
            self::markTestSkipped('Runs in the "every official plugin enabled" build (SIGHTPRESS_TEST_PLUGINS=enabled).');
        }

        $kernel = self::bootKernel();
        self::assertSame(PluginsEnabledKernel::pluginBundles(), self::pluginBundlesOf($kernel));
        if ([] !== InstalledVersions::getInstalledPackagesByType('sightpress-plugin')) {
            // The integration build installs every official plugin from the sibling checkouts.
            self::assertSame(self::OFFICIAL_PLUGIN_BUNDLES, array_values(array_intersect(self::OFFICIAL_PLUGIN_BUNDLES, self::pluginBundlesOf($kernel))));
        }
        self::assertSame(404, self::handle('http://attacker.example/')->getStatusCode());
    }

    public function testThePluginListNamesWhatIsInstalledAndWhatIsNot(): void
    {
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);

        self::assertSame(0, $tester->run(['command' => 'sightpress:plugin:list']));
        $installed = \count(InstalledVersions::getInstalledPackagesByType('sightpress-plugin'));
        self::assertStringContainsString(0 === $installed ? 'Installed plugins: none.' : \sprintf('Installed plugins (%d):', $installed), $tester->getDisplay());
        self::assertStringContainsString(8 === $installed ? 'Every official plugin is installed.' : 'Install it with: php bin/console sightpress:plugin:install', $tester->getDisplay());
    }

    public function testAnUnknownHostGetsA404WithTheSecurityHeaders(): void
    {
        $response = self::handle('http://attacker.example/');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame("Not found\n", $response->getContent());
        self::assertStringContainsString("default-src 'none'", (string) $response->headers->get('Content-Security-Policy'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertTrue($response->headers->has('Referrer-Policy'));
        self::assertTrue($response->headers->has('Permissions-Policy'));
    }

    public function testSessionCookiesAreSecureByDefault(): void
    {
        $options = self::getContainer()->getParameter('session.storage.options');

        self::assertIsArray($options);
        self::assertTrue($options['cookie_httponly']);
        self::assertSame('lax', $options['cookie_samesite']);
    }

    public function testTheProductionContainerCompilesWithoutDebug(): void
    {
        $kernel = new Kernel('prod', false);
        $kernel->boot();

        try {
            self::assertFalse($kernel->isDebug());
            $options = $kernel->getContainer()->getParameter('session.storage.options');
            self::assertIsArray($options);
            self::assertTrue($options['cookie_secure'], 'Session cookies are Secure in production.');
        } finally {
            $kernel->shutdown();
        }
    }

    private static function handle(string $uri): Response
    {
        return self::bootKernel()->handle(Request::create($uri));
    }
}
