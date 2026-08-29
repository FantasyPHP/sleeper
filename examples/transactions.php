<?php

declare(strict_types=1);

use Fantasyphp\Sleeper\Data\Api\TradedPick;
use Fantasyphp\Sleeper\Enums\TransactionStatus;
use Fantasyphp\Sleeper\SleeperConnector;

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/ids.php';

$connector = new SleeperConnector;

/**
 * Everything that moved in one week, and every pick that has ever moved.
 *
 * There is no whole-season transactions endpoint, so a league's history is one
 * call per week. Failed claims come back alongside successful ones -- somebody
 * losing a waiver is a fact about the week, not an error -- so check the status
 * before treating an add as having happened.
 *
 * The dynasty league below is used for the traded picks, because only a dynasty
 * league trades them.
 */
$leagueId = sleeper_league_id();
$dynastyLeagueId = sleeper_id('SLEEPER_DYNASTY_LEAGUE_ID', '337383787396628480');
$week = 15;

printf("Week %d\n\n", $week);

foreach ($connector->transactions($leagueId, $week) as $transaction) {
    printf(
        "  %s  %-11s %-8s roster%s %s\n",
        $transaction->statusUpdatedAt()->format('D H:i'),
        $transaction->type,
        $transaction->status,
        count($transaction->rosterIds) === 1 ? ' ' : 's',
        implode(', ', $transaction->rosterIds),
    );

    // Keyed by player and valued by roster, which is the only way one map can
    // describe a multi-team trade.
    foreach ($transaction->adds as $playerId => $rosterId) {
        printf("      + %-6s to roster %d\n", $playerId, $rosterId);
    }

    foreach ($transaction->drops as $playerId => $rosterId) {
        printf("      - %-6s from roster %d\n", $playerId, $rosterId);
    }

    if ($transaction->waiverBid() !== null) {
        printf("      bid %d\n", $transaction->waiverBid());
    }

    if ($transaction->state() === TransactionStatus::Failed) {
        printf("      %s\n", $transaction->metadata['notes'] ?? 'no reason given');
    }
}

printf("\nPicks that have changed hands in the dynasty league\n\n");

$picks = $connector->tradedPicks($dynastyLeagueId);

// Sort by the season they belong to: a dynasty league trades picks years ahead,
// which is why the season is a string rather than a number.
usort($picks, static fn (TradedPick $a, TradedPick $b): int => [$a->season, $a->round]
    <=> [$b->season, $b->round]);

foreach ($picks as $pick) {
    printf(
        "  %s round %d  originally roster %-2d  now roster %-2d  from roster %s\n",
        $pick->season,
        $pick->round,
        $pick->rosterId,
        $pick->ownerId,
        // Not necessarily the originating team: a pick can be traded on.
        $pick->previousOwnerId ?? '-',
    );
}
