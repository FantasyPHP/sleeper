<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests;

use RuntimeException;

/**
 * How this run of the suite treats Saloon fixtures.
 *
 * The same tests run in both modes. Offline they replay recorded fixtures;
 * in record mode a missing fixture is fetched from the live API and written
 * to disk, so fixtures stay real payloads rather than hand-written guesses.
 *
 * Sleeper's read API takes no credentials, so recording needs nothing beyond
 * network access — but it does count against the published rate limit.
 */
enum FixtureMode: string
{
    /**
     * Fixtures must already exist. Nothing touches the network.
     */
    case Strict = 'strict';

    /**
     * Missing fixtures are recorded from the live API.
     */
    case Record = 'record';

    public const string ENV_VAR = 'FANTASY_FIXTURES';

    public static function current(): self
    {
        $mode = self::env(self::ENV_VAR);

        if ($mode === null) {
            return self::Strict;
        }

        return self::tryFrom($mode) ?? throw new RuntimeException(sprintf(
            '%s must be one of "%s", got "%s".',
            self::ENV_VAR,
            implode('", "', array_column(self::cases(), 'value')),
            $mode,
        ));
    }

    private static function env(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
