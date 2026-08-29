<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Enums;

/**
 * A position, as Sleeper labels it.
 *
 * Every distinct value in the full player map, which is far more than the
 * fantasy-relevant handful: Sleeper carries linemen, long snappers and punters
 * because it carries whole NFL rosters, not just draftable players.
 *
 * A player's `position` is his real one; his `fantasy_positions` are the slots
 * he is eligible for, and the two need not match -- `DEF` only ever appears in
 * the latter for a team defense, whose "player" ID is the team abbreviation.
 */
enum NflPosition: string
{
    case Quarterback = 'QB';

    case RunningBack = 'RB';

    case Fullback = 'FB';

    case WideReceiver = 'WR';

    case TightEnd = 'TE';

    case Kicker = 'K';

    case Punter = 'P';

    /**
     * A player Sleeper lists as both kicker and punter.
     */
    case KickerPunter = 'K/P';

    case LongSnapper = 'LS';

    case TeamDefense = 'DEF';

    case OffensiveLine = 'OL';

    case OffensiveTackle = 'OT';

    case OffensiveGuard = 'OG';

    case Guard = 'G';

    case Tackle = 'T';

    case Center = 'C';

    case DefensiveLine = 'DL';

    case DefensiveEnd = 'DE';

    case DefensiveTackle = 'DT';

    case NoseTackle = 'NT';

    case Linebacker = 'LB';

    case InsideLinebacker = 'ILB';

    case OutsideLinebacker = 'OLB';

    /**
     * A hybrid edge rusher. Appears only among fantasy positions, never as a
     * player's own position.
     */
    case Leo = 'LEO';

    case DefensiveBack = 'DB';

    case Cornerback = 'CB';

    case Safety = 'S';

    case FreeSafety = 'FS';

    case StrongSafety = 'SS';

    /**
     * Athlete -- what Sleeper uses for a prospect whose position is not settled.
     */
    case Athlete = 'ATH';
}
