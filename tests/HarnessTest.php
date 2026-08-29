<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Saloon\MockConfig;

/**
 * Covers the test harness itself, not src/, so it declares no CoversClass --
 * FixtureMode lives in tests/ and is not a valid coverage target.
 */
final class HarnessTest extends TestCase
{
    #[Test]
    public function the_suite_defaults_to_offline_mode(): void
    {
        $this->forgetVar(FixtureMode::ENV_VAR);

        self::assertSame(FixtureMode::Strict, FixtureMode::current());
    }

    #[Test]
    public function an_unrecognised_mode_is_rejected(): void
    {
        $this->setVar(FixtureMode::ENV_VAR, 'sometimes');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FANTASY_FIXTURES must be one of "strict", "record", got "sometimes".');

        FixtureMode::current();
    }

    #[Test]
    public function the_mode_is_read_from_every_source_the_project_reads(): void
    {
        $this->forgetVar(FixtureMode::ENV_VAR);

        putenv(FixtureMode::ENV_VAR.'=record');

        self::assertSame(FixtureMode::Record, FixtureMode::current());

        $_ENV[FixtureMode::ENV_VAR] = 'strict';

        self::assertSame(FixtureMode::Strict, FixtureMode::current());

        $_SERVER[FixtureMode::ENV_VAR] = 'record';

        self::assertSame(FixtureMode::Record, FixtureMode::current());
    }

    /**
     * An empty value is not a mode. Left unguarded it would reach tryFrom and
     * blow up as an unrecognised mode instead of falling back to offline.
     */
    #[Test]
    public function an_empty_mode_falls_back_to_offline(): void
    {
        $this->setVar(FixtureMode::ENV_VAR, '');

        self::assertSame(FixtureMode::Strict, FixtureMode::current());
    }

    #[Test]
    public function offline_runs_fail_on_a_missing_fixture_rather_than_calling_the_api(): void
    {
        if (FixtureMode::current() !== FixtureMode::Strict) {
            self::markTestSkipped('Recording deliberately allows missing fixtures.');
        }

        self::assertTrue(MockConfig::isThrowingOnMissingFixtures());
    }

    #[Test]
    public function fixtures_are_stored_under_the_tests_directory(): void
    {
        self::assertSame(
            __DIR__.'/Fixtures/Saloon',
            MockConfig::getFixturePath(),
        );
    }

    #[Test]
    public function the_sleeper_namespace_maps_to_the_src_directory(): void
    {
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);

        self::assertInstanceOf(ClassLoader::class, $loader);

        $prefixes = $loader->getPrefixesPsr4();

        self::assertArrayHasKey('Fantasyphp\\Sleeper\\', $prefixes);
        self::assertSame(
            [realpath(__DIR__.'/../src')],
            array_map(realpath(...), $prefixes['Fantasyphp\\Sleeper\\']),
        );
    }
}
