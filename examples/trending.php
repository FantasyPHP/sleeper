<?php

declare(strict_types=1);

use Fantasyphp\Sleeper\Enums\NflPosition;
use Fantasyphp\Sleeper\Enums\Sport;
use Fantasyphp\Sleeper\Enums\TrendingType;
use Fantasyphp\Sleeper\SleeperConnector;

require_once __DIR__.'/../vendor/autoload.php';

$connector = new SleeperConnector;

/**
 * Who is being added and dropped across every Sleeper league.
 *
 * The endpoint returns IDs and counts and nothing else, so the names below come
 * from a second call. Only two positions are fetched rather than the whole
 * player map, which keeps this to a few KB instead of 5MB -- most of what
 * trends is a running back or a wide receiver.
 *
 * Sleeper asks for attribution if you publish this data.
 */
$names = [];

foreach ([NflPosition::RunningBack, NflPosition::WideReceiver] as $position) {
    foreach ($connector->players(Sport::Nfl, $position) as $player) {
        $names[$player->playerId] = sprintf('%s (%s, %s)', $player->name(), $player->position ?? '-', $player->team ?? 'FA');
    }
}

foreach ([[TrendingType::Add, 'Added'], [TrendingType::Drop, 'Dropped']] as [$direction, $title]) {
    // Leaving lookbackHours null takes Sleeper's own 24-hour default.
    $trending = $connector->trendingPlayers(Sport::Nfl, $direction, limit: 10);

    printf("%s in the last day\n", $title);

    foreach ($trending as $player) {
        printf(
            "  %8s  %s\n",
            number_format($player->count),
            // Unresolved for anyone outside the two positions fetched above.
            $names[$player->playerId] ?? sprintf('player %s', $player->playerId),
        );
    }

    printf("\n");
}
