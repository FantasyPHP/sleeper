<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Enums;

/**
 * Where a league is in its own life cycle.
 *
 * Not written down anywhere in the docs; these are the values the API has been
 * seen to send. Read through `League::state()`, which uses `tryFrom` so a
 * status Sleeper adds later cannot cost you the rest of the league.
 */
enum LeagueStatus: string
{
    case PreDraft = 'pre_draft';

    case Drafting = 'drafting';

    case InSeason = 'in_season';

    case PostSeason = 'post_season';

    case Complete = 'complete';
}
