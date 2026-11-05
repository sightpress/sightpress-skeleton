<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Checks the promises the skeleton makes (CLAUDE.md, PLAN.md §3 and §3.4):
 * it requires the core only, suggests the official plugins, runs the
 * installer from Composer without ever failing, and holds no product code.
 */
final class ProjectTest extends TestCase
{
    private const array OFFICIAL_PLUGINS = [
        'sightpress/plugin-ai',
        'sightpress/plugin-articles',
        'sightpress/plugin-community',
        'sightpress/plugin-forms',
        'sightpress/plugin-image-finder',
        'sightpress/plugin-import',
        'sightpress/plugin-newsletter',
        'sightpress/plugin-notifications',
    ];

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    /** @return array<string, mixed> */
    private static function json(string $file): array
    {
        $data = json_decode((string) file_get_contents(self::root().'/'.$file), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function section(array $data, string $key): array
    {
        $section = $data[$key] ?? null;
        self::assertIsArray($section, "\"$key\" is missing.");

        /** @var array<string, mixed> $section */
        return $section;
    }

    public function testTheProjectRequiresTheCoreAndNoPlugin(): void
    {
        $composer = self::json('composer.json');

        self::assertSame('sightpress/skeleton', $composer['name']);
        self::assertSame('project', $composer['type']);
        self::assertSame('GPL-2.0-or-later', $composer['license']);
        self::assertArrayNotHasKey('version', $composer, 'Tags are the only version source.');

        $sightpress = array_values(array_filter(
            array_keys(self::section($composer, 'require') + self::section($composer, 'require-dev')),
            static fn (string $package): bool => str_starts_with($package, 'sightpress/'),
        ));
        self::assertSame(['sightpress/core'], $sightpress);
    }

    public function testTheOfficialPluginsAreSuggestedWithADescription(): void
    {
        $suggest = self::section(self::json('composer.json'), 'suggest');

        $packages = array_keys($suggest);
        sort($packages);
        self::assertSame(self::OFFICIAL_PLUGINS, $packages);
        foreach ($suggest as $package => $description) {
            self::assertIsString($description);
            self::assertNotSame('', trim($description), "$package has no description.");
        }
    }

    public function testTheComposerHookRunsTheInstallerAndNeverFails(): void
    {
        $scripts = self::section(self::json('composer.json'), 'scripts');

        self::assertSame(['@auto-scripts', '@sightpress:install'], $scripts['post-install-cmd']);
        self::assertSame([
            'Composer\Config::disableProcessTimeout',
            '@php bin/console sightpress:install --from-composer || exit 0',
        ], $scripts['sightpress:install']);
    }

    public function testTheDevelopmentManifestUsesEverySiblingCheckout(): void
    {
        $composer = self::json('composer.json');
        $dev = self::json('composer.dev.json');

        $urls = array_map(
            static fn (mixed $repository): mixed => \is_array($repository) ? ($repository['url'] ?? null) : null,
            array_values(self::section($dev, 'repositories')),
        );
        $expected = ['../plugin-core', '../core'];
        foreach (self::OFFICIAL_PLUGINS as $package) {
            $expected[] = '../'.substr($package, \strlen('sightpress/'));
        }
        self::assertEqualsCanonicalizing($expected, $urls);

        $require = self::section($dev, 'require');
        foreach (['sightpress/core', 'sightpress/plugin-core', ...self::OFFICIAL_PLUGINS] as $package) {
            self::assertArrayHasKey($package, $require, "composer.dev.json must require $package.");
        }

        // Everything else stays the same as the released project.
        foreach (['autoload', 'autoload-dev', 'scripts', 'config', 'extra', 'require-dev'] as $key) {
            self::assertSame($composer[$key], $dev[$key], "\"$key\" differs between composer.json and composer.dev.json.");
        }
        self::assertSame([], array_diff_key(self::section($composer, 'require'), $require));
    }

    public function testTheSkeletonHoldsNoProductCode(): void
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root().'/src', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            $files[] = substr($file->getPathname(), \strlen(self::root()) + 1);
        }

        self::assertSame(['src/Kernel.php'], $files);
        self::assertDirectoryDoesNotExist(self::root().'/templates', 'Templates belong in sightpress/core or a plugin.');
        self::assertDirectoryDoesNotExist(self::root().'/migrations', 'Migrations belong in sightpress/core or a plugin.');
    }

    public function testCommittedEnvironmentFilesHoldNoProductionSecret(): void
    {
        $env = (string) file_get_contents(self::root().'/.env');

        self::assertMatchesRegularExpression('/^APP_SECRET=$/m', $env);
        self::assertMatchesRegularExpression('/^APP_ENV=dev$/m', $env);
    }

    public function testTheConsoleRuns(): void
    {
        $process = new Process([\PHP_BINARY, 'bin/console', 'list', '--no-ansi', '--env=test'], self::root());
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString('doctrine:migrations:migrate', $process->getOutput());
    }

    public function testTheDevelopmentServicesArePostgresMailpitAndMinio(): void
    {
        $compose = Yaml::parseFile(self::root().'/compose.yaml');
        self::assertIsArray($compose);
        $services = self::section($compose, 'services');

        $images = array_map(static fn (mixed $service): string => \is_array($service) && \is_string($service['image'] ?? null) ? $service['image'] : '', $services);
        self::assertSame('postgres:17-alpine', $images['database']);
        self::assertStringStartsWith('axllent/mailpit:', $images['mailer']);
        self::assertStringContainsString('minio', $images['storage']);

        foreach ($services as $name => $service) {
            self::assertIsArray($service);
            foreach ((array) ($service['ports'] ?? []) as $port) {
                self::assertIsString($port);
                self::assertStringStartsWith('127.0.0.1:', $port, "The development service \"$name\" is only reachable from this machine.");
            }
        }
    }

    public function testTheProductionImageRunsAsAnUnprivilegedUserWithoutTheInstaller(): void
    {
        $dockerfile = (string) file_get_contents(self::root().'/Dockerfile');

        self::assertMatchesRegularExpression('/^USER sightpress$/m', $dockerfile);
        self::assertStringContainsString('composer install --no-dev', $dockerfile);
        self::assertStringContainsString('--classmap-authoritative', $dockerfile);
        self::assertDoesNotMatchRegularExpression('/^RUN .*sightpress:install/m', $dockerfile, 'The image build never runs the installer.');
        self::assertStringContainsString('opcache.preload = /app/config/preload.php', (string) file_get_contents(self::root().'/docker/frankenphp/conf.d/10-sightpress.ini'));

        $compose = Yaml::parseFile(self::root().'/compose.prod.yaml');
        self::assertIsArray($compose);
        $web = self::section(self::section($compose, 'services'), 'web');
        $environment = self::section($web, 'environment');
        self::assertIsString($environment['APP_SECRET']);
        self::assertStringStartsWith('${APP_SECRET:?', $environment['APP_SECRET'], 'Production refuses to start without its own secret.');
    }
}
