<?php

declare(strict_types=1);

use Fantasyphp\Sleeper\Data\Api\LeagueUser;
use Fantasyphp\Sleeper\Data\Api\Matchup;
use Fantasyphp\Sleeper\SleeperConnector;

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/ids.php';

$connector = new SleeperConnector;

/**
 * One week's fixtures.
 *
 * The endpoint returns a row per team rather than a row per fixture, so the
 * pairing below is the caller's job: two rows sharing a `matchupId` are playing
 * each other, and a row with none is a team the playoffs have left without an
 * opponent.
 */
$leagueId = sleeper_league_id();
$week = 14;

$rosters = $connector->rosters($leagueId);
$members = $connector->leagueUsers($leagueId);
$matchups = $connector->matchups($leagueId, $week);

$ownerByRoster = [];

foreach ($rosters as $roster) {
    $ownerByRoster[$roster->rosterId] = $roster->ownerId;
}

$memberByUser = [];

foreach ($members as $member) {
    $memberByUser[$member->userId] = $member;
}

$name = static function (int $rosterId) use ($ownerByRoster, $memberByUser): string {
    $member = $memberByUser[$ownerByRoster[$rosterId] ?? ''] ?? null;

    return $member instanceof LeagueUser
        ? $member->teamName() ?? $member->displayName
        : sprintf('roster %d', $rosterId);
};

/** @var array<int, list<Matchup>> $fixtures */
$fixtures = [];
$idle = [];

foreach ($matchups as $matchup) {
    if ($matchup->matchupId === null) {
        $idle[] = $matchup;

        continue;
    }

    $fixtures[$matchup->matchupId][] = $matchup;
}

printf("Week %d\n\n", $week);

foreach ($fixtures as $matchupId => $sides) {
    usort($sides, static fn (Matchup $a, Matchup $b): int => $b->score() <=> $a->score());

    printf("  match %d\n", $matchupId);

    foreach ($sides as $side) {
        printf(
            "    %s %7.2f%s\n",
            mb_str_pad(mb_substr($name($side->rosterId), 0, 22), 22),
            // score(), not points: a commissioner override replaces the
            // computed total and is what decides the week.
            $side->score(),
            $side->customPoints === null ? '' : ' (adjusted)',
        );
    }
}

if ($idle !== []) {
    printf("\n  no fixture this week (knocked out, but still scored)\n");

    foreach ($idle as $side) {
        printf(
            "    %s %7.2f\n",
            mb_str_pad(mb_substr($name($side->rosterId), 0, 22), 22),
            $side->score(),
        );
    }
}
