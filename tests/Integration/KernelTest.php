<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Kernel;
use SightPress\Core\SightPressCoreBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Kernel\BundleInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\BundleAdapter;

final class KernelTest extends KernelTestCase
{
    public function testTheKernelExtendsTheCoreKernelAndRegistersEveryCoreBundleOnce(): void
    {
        $kernel = self::bootKernel();

        self::assertInstanceOf(Kernel::class, $kernel);
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

    public function testNoPluginIsInstalledByDefault(): void
    {
        $kernel = self::bootKernel();

        foreach ($kernel->getBundles() as $bundle) {
            $bundle = $bundle instanceof BundleAdapter ? $bundle->getInnerBundle() : $bundle;
            self::assertStringStartsNotWith('SightPress\Plugin\\', $bundle::class);
        }
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
