<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Enums;

/**
 * Where a season currently is.
 *
 * The docs name three of these. `off` is the fourth: it is what `/state/nba`
 * and `/state/nhl` answer for most of the year, and it is not written down
 * anywhere. Read through `SportState::type()`, which uses `tryFrom` so a fifth
 * one nobody has seen yet cannot break an otherwise usable state.
 */
enum SeasonType: string
{
    case PreSeason = 'pre';

    case RegularSeason = 'regular';

    case PostSeason = 'post';

    case OffSeason = 'off';
}
