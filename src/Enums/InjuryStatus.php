<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Enums;

/**
 * A player's injury designation.
 *
 * Undocumented; these are every distinct value in the full player map. Half of
 * them are roster designations rather than injuries in the NFL's sense --
 * `Sus` is a suspension and `DNR` is did-not-report -- but Sleeper files them
 * all under the same field.
 *
 * Sleeper sends an empty string rather than null for "no designation" inside a
 * draft pick's metadata, and null for the same thing on a player record. Both
 * read as absent here.
 */
enum InjuryStatus: string
{
    case Questionable = 'Questionable';

    case Doubtful = 'Doubtful';

    case Out = 'Out';

    case InjuredReserve = 'IR';

    /**
     * Physically unable to perform.
     */
    case PhysicallyUnableToPerform = 'PUP';

    /**
     * Not active -- the catch-all for a player who is not available and has no
     * more specific designation.
     */
    case NotActive = 'NA';

    case Suspended = 'Sus';

    /**
     * Did not report.
     */
    case DidNotReport = 'DNR';

    case Covid = 'COV';
}
