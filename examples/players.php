<?php

declare(strict_types=1);

use Fantasyphp\Sleeper\Data\Api\Player;
use Fantasyphp\Sleeper\Enums\NflPosition;
use Fantasyphp\Sleeper\Enums\Sport;
use Fantasyphp\Sleeper\SleeperConnector;

require_once __DIR__.'/../vendor/autoload.php';

$connector = new SleeperConnector;

/**
 * The player map, narrowed to one position.
 *
 * Ask for a position. The unfiltered map is roughly 5MB across twelve thousand
 * players, most of them long retired, and the docs ask that it be called at
 * most once a day -- `$connector->everyPlayer()` is there if you really want
 * it, named so you cannot reach it by accident.
 *
 * Note what the filter matches: a player's *fantasy* positions, not his listed
 * one. Asking for LEO below returns players whose position is DE.
 */
$kickers = $connector->players(Sport::Nfl, NflPosition::Kicker);

printf("%d active kickers\n\n", count($kickers));

// Sleeper's own ordering signal: lower is more prominent.
uasort(
    $kickers,
    static fn (Player $a, Player $b): int => ($a->searchRank ?? PHP_INT_MAX) <=> ($b->searchRank ?? PHP_INT_MAX),
);

foreach (array_slice($kickers, 0, 10, preserve_keys: true) as $id => $player) {
    printf(
        "  %-6s %s %-4s %-3s  age %-4s %s\n",
        // The key PHP gives back, which is an int for a numeric ID. The DTO's
        // own playerId is the authoritative string.
        (string) $id,
        mb_str_pad(mb_substr($player->name(), 0, 22), 22),
        $player->position ?? '-',
        $player->team ?? 'FA',
        $player->age ?? '-',
        // Narrowed with tryFrom, so a designation this client has not seen
        // leaves the raw string readable rather than blanking the row.
        $player->injury() === null ? $player->injuryStatus ?? '' : $player->injury()->value,
    );
}

/**
 * Team defenses, the case that breaks anything assuming a player ID is a
 * number: theirs is the team abbreviation.
 */
$defenses = $connector->players(Sport::Nfl, NflPosition::TeamDefense);

printf("\n%d team defenses, keyed by team rather than by number:\n  %s\n", count($defenses), implode(', ', array_map(
    static fn (Player $player): string => $player->playerId,
    array_slice($defenses, 0, 12),
)));
