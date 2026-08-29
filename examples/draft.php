<?php

declare(strict_types=1);

use Fantasyphp\Sleeper\Enums\DraftStatus;
use Fantasyphp\Sleeper\SleeperConnector;

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/ids.php';

$connector = new SleeperConnector;

/**
 * A league's drafts, and the picks made in the most recent one.
 *
 * A league's own `draftId` is only its latest draft. A dynasty league holds one
 * every season, and `/league/{id}/drafts` is the only way to see the rest.
 *
 * Nothing here needs the player map: every pick carries a snapshot of the
 * player as he was when it was made. That snapshot is stale by design -- the
 * team below is the one the player was on in 2019.
 */
$leagueId = sleeper_dynasty_league_id();

$drafts = $connector->leagueDrafts($leagueId);

printf("%d draft(s)\n\n", count($drafts));

foreach ($drafts as $draft) {
    printf(
        "  %s  %s %s, %d rounds, %d teams -- %s%s\n",
        $draft->draftId,
        $draft->season,
        $draft->type,
        $draft->settings['rounds'] ?? 0,
        $draft->settings['teams'] ?? 0,
        $draft->status,
        // Null for a draft nobody has scheduled yet.
        $draft->startsAt() === null ? '' : $draft->startsAt()->format(' (D j M Y)'),
    );
}

$latest = $drafts[0];

if ($latest->state() !== DraftStatus::Complete) {
    printf("\nThe most recent draft has not finished, so there is nothing to list.\n");

    return;
}

printf("\n%s, round by round\n\n", $latest->metadata['name'] ?? $latest->draftId);

$byRound = [];

foreach ($connector->draftPicks($latest->draftId) as $pick) {
    $byRound[$pick->round][] = $pick;
}

foreach ($byRound as $round => $picks) {
    printf("  Round %d\n", $round);

    foreach ($picks as $pick) {
        printf(
            "    %-3d slot %-2d  %s %-4s %-4s%s\n",
            $pick->pickNumber,
            $pick->draftSlot,
            mb_str_pad(mb_substr($pick->player->name(), 0, 22), 22),
            $pick->player->position ?? '-',
            $pick->player->team ?? 'FA',
            $pick->isKeeper ? '  [keeper]' : '',
        );
    }
}

// Picks that changed hands before the draft ran, so slot 1 need not belong to
// the team the draft order gave it to. Every ID on one is a roster ID.
$traded = $connector->draftTradedPicks($latest->draftId);

if ($traded !== []) {
    printf("\n  %d pick(s) were traded before this draft ran\n", count($traded));

    foreach ($traded as $pick) {
        printf(
            "    round %d  roster %d's pick, now roster %d's\n",
            $pick->round,
            $pick->rosterId,
            $pick->ownerId,
        );
    }
}
