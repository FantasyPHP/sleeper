<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Enums;

/**
 * The sports the API is keyed by, as they appear in a `{sport}` path segment.
 *
 * Only NFL is a first-class citizen. The docs say leagues and drafts support
 * "nfl" and nothing else, and everything below NFL here is reachable only
 * through `/state/{sport}` and `/players/{sport}`. They exist so the path
 * segment is typed rather than stringly, not because they are exercised.
 */
enum Sport: string
{
    case Nfl = 'nfl';

    case Nba = 'nba';

    case Mlb = 'mlb';

    case Nhl = 'nhl';

    case Wnba = 'wnba';

    /**
     * League of Legends Championship Series. Sleeper ran fantasy esports and
     * the endpoint still answers, which is worth knowing because it is the
     * sport whose state payload is least like the others.
     */
    case Lcs = 'lcs';
}
