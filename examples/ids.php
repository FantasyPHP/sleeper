<?php

declare(strict_types=1);

use Dotenv\Dotenv;

/**
 * The IDs the example scripts run against.
 *
 * Defaults are public leagues that have already finished, so every script
 * prints something out of the box and its output does not change under you.
 * Point them at your own by putting these in `.env` at the repo root:
 *
 *     SLEEPER_LEAGUE_ID=1399461312144084992
 *     SLEEPER_USER_ID=1399295919517605888
 *
 * Anything already exported wins over the file, so a one-off run is still
 * `SLEEPER_LEAGUE_ID=… php examples/league.php`.
 *
 * These are not secrets -- Sleeper's read API takes no credentials and league
 * IDs are public. `.env` is gitignored anyway, so pointing the examples at your
 * own league does not put it in anybody else's checkout.
 *
 * The test suite reads none of this. Fixture IDs are constants in the tests
 * that assert against them, so `composer test:record` needs a network
 * connection and no local setup at all.
 *
 * Note that a league still in its preseason has less to show than a finished
 * one: no matchups, no transactions and no draft picks, so those scripts will
 * print very little.
 */
Dotenv::createImmutable(__DIR__.'/..')->safeLoad();

function sleeper_id(string $variable, string $default): string
{
    $value = $_SERVER[$variable] ?? $_ENV[$variable] ?? getenv($variable);

    return is_string($value) && $value !== '' ? $value : $default;
}

/**
 * A completed public league from 2018: rosters, matchups, brackets and a full
 * transaction history.
 */
function sleeper_league_id(): string
{
    return sleeper_id('SLEEPER_LEAGUE_ID', '289646328504385536');
}

function sleeper_user_id(): string
{
    return sleeper_id('SLEEPER_USER_ID', '189140835533586432');
}

/**
 * A dynasty league, for the scripts that need more than one draft or a traded
 * pick to look at.
 */
function sleeper_dynasty_league_id(): string
{
    return sleeper_id('SLEEPER_DYNASTY_LEAGUE_ID', '384109584450625536');
}
