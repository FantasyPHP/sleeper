<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Enums;

/**
 * Where a player stands with his NFL club, as opposed to with a fantasy team.
 *
 * Undocumented; these are every distinct value in the full player map. Note
 * `Inactive` covers roughly a quarter of it -- the map keeps everybody who has
 * ever been on a roster, not just everybody currently on one.
 */
enum RosterStatus: string
{
    case Active = 'Active';

    case Inactive = 'Inactive';

    case InjuredReserve = 'Injured Reserve';

    case PracticeSquad = 'Practice Squad';

    case PhysicallyUnableToPerform = 'Physically Unable to Perform';

    case NonFootballInjury = 'Non Football Injury';
}
