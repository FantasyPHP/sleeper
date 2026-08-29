<?php

declare(strict_types=1);

use Fantasyphp\Sleeper\Data\Api\BracketMatchup;
use Fantasyphp\Sleeper\Data\Api\LeagueUser;
use Fantasyphp\Sleeper\SleeperConnector;

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/ids.php';

$connector = new SleeperConnector;

/**
 * Both playoff brackets.
 *
 * Note the route the losers bracket actually lives on. The docs' HTTP Request
 * line says `loses_bracket`; that 404s, and `losers_bracket` is what works.
 *
 * A bracket speaks only in roster IDs, so putting names on it means fetching
 * rosters and members too.
 */
$leagueId = sleeper_league_id();

$rosters = $connector->rosters($leagueId);
$members = $connector->leagueUsers($leagueId);

$ownerByRoster = [];

foreach ($rosters as $roster) {
    $ownerByRoster[$roster->rosterId] = $roster->ownerId;
}

$memberByUser = [];

foreach ($members as $member) {
    $memberByUser[$member->userId] = $member;
}

$name = static function (?int $rosterId) use ($ownerByRoster, $memberByUser): string {
    if ($rosterId === null) {
        return 'tbd';
    }

    $member = $memberByUser[$ownerByRoster[$rosterId] ?? ''] ?? null;

    return $member instanceof LeagueUser
        ? $member->teamName() ?? $member->displayName
        : sprintf('roster %d', $rosterId);
};

$progression = static function (BracketMatchup $matchup): void {
    foreach (['t1' => $matchup->team1From, 't2' => $matchup->team2From] as $slot => $from) {
        if (! $from instanceof Fantasyphp\Sleeper\Data\Api\BracketSlot) {
            continue;
        }

        printf(
            "%s%s comes from the %s of match %d\n",
            str_repeat(' ', 20),
            $slot,
            $from->winner ? 'winner' : 'loser',
            $from->matchId,
        );
    }
};

$brackets = [
    'Championship' => $connector->winnersBracket($leagueId),
    'Consolation' => $connector->losersBracket($leagueId),
];

foreach ($brackets as $title => $bracket) {
    printf("%s\n", $title);

    foreach ($bracket as $matchup) {
        printf(
            "  round %d  match %-2d  %s v %s%s\n",
            $matchup->round,
            $matchup->matchId,
            mb_str_pad(mb_substr($name($matchup->team1RosterId), 0, 20), 20),
            mb_str_pad(mb_substr($name($matchup->team2RosterId), 0, 20), 20),
            // Only the matches that settle a final position carry a placement.
            $matchup->placement === null ? '' : sprintf('  [places %d]', $matchup->placement),
        );

        if ($matchup->winnerRosterId !== null) {
            printf("%s won by %s\n", str_repeat(' ', 20), $name($matchup->winnerRosterId));
        }

        // Kept even after the match is played, so a finished bracket still
        // says how each team got there.
        $progression($matchup);
    }

    printf("\n");
}
