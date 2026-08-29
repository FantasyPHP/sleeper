<?php

declare(strict_types=1);

use Fantasyphp\Sleeper\Data\Api\LeagueUser;
use Fantasyphp\Sleeper\Data\Api\Roster;
use Fantasyphp\Sleeper\SleeperConnector;

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/ids.php';

$connector = new SleeperConnector;

/**
 * A league's standings, which take three calls rather than one.
 *
 * Sleeper joins nothing up for you: rosters know their `ownerId`, members know
 * their `userId`, and neither knows the other's name. The standings below are
 * that join, done here because there is nowhere else to do it.
 */
$leagueId = sleeper_league_id();

$league = $connector->league($leagueId);
$rosters = $connector->rosters($leagueId);
$members = $connector->leagueUsers($leagueId);

printf(
    "%s -- %s %s, %d teams, %s\n\n",
    $league->name,
    $league->season,
    $league->seasonType,
    $league->totalRosters,
    $league->status,
);

// A league can have more members than rosters, so index rather than zip.
$byUserId = [];

foreach ($members as $member) {
    $byUserId[$member->userId] = $member;
}

// Points are recombined from the two halves Sleeper stores them in, so these
// sort correctly rather than by their whole part alone.
usort($rosters, static fn (Roster $a, Roster $b): int => [$b->settings->wins, $b->settings->pointsFor]
    <=> [$a->settings->wins, $a->settings->pointsFor]);

printf("%-3s %-22s %-7s %9s %9s %9s\n", '#', 'team', 'record', 'for', 'against', 'best');

foreach ($rosters as $position => $roster) {
    // Nullable, because a league can carry an orphan team nobody has claimed.
    $member = $roster->ownerId === null ? null : $byUserId[$roster->ownerId] ?? null;
    $team = $member instanceof LeagueUser ? $member->teamName() ?? $member->displayName : 'unclaimed';

    printf(
        "%-3d %s %-7s %9.2f %9.2f %9s\n",
        $position + 1,
        // Team names are user-supplied and full of emoji, so pad by characters
        // rather than by bytes or the columns come out ragged.
        mb_str_pad(mb_substr($team, 0, 22), 22),
        sprintf('%d-%d-%d', $roster->settings->wins, $roster->settings->losses, $roster->settings->ties),
        $roster->settings->pointsFor,
        $roster->settings->pointsAgainst,
        // Not every league computes the perfect-lineup score.
        $roster->settings->maximumPoints === null
            ? '-'
            : sprintf('%.2f', $roster->settings->maximumPoints),
    );
}
