<?php

declare(strict_types=1);

use Fantasyphp\Sleeper\Enums\Sport;
use Fantasyphp\Sleeper\SleeperConnector;

require_once __DIR__.'/../vendor/autoload.php';

$connector = new SleeperConnector;

/**
 * GET /state/{sport}: which week and season a sport is currently in.
 *
 * Worth calling before anything that takes a week or a season. Week numbers
 * restart at 1 in the preseason, so "week 3" on its own does not say which
 * week 3 you are looking at -- `season_type` is the half that does.
 */
foreach (Sport::cases() as $sport) {
    $state = $connector->state($sport);

    printf(
        "%-5s %s season %s, week %d%s\n",
        $sport->value,
        $state->seasonType,
        $state->season,
        $state->week,
        $state->displayWeek !== null && $state->displayWeek !== $state->week
            ? sprintf(' (displayed as %d)', $state->displayWeek)
            : '',
    );

    // Null for a sport whose next season has not been scheduled yet, which
    // is most of them for most of the year.
    if ($state->seasonStartDate !== null) {
        printf("      regular season starts %s\n", $state->seasonStartDate);
    }

    // Narrowed with tryFrom rather than from, because the documented set of
    // three is wrong: `off` is real and undocumented, so there may be others.
    if (! $state->type() instanceof Fantasyphp\Sleeper\Enums\SeasonType) {
        printf("      season type \"%s\" is one this client does not know\n", $state->seasonType);
    }
}
