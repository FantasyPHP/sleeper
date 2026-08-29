<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Enums;

/**
 * How the draft order runs.
 *
 * `snake` reverses every round, `linear` keeps the same order throughout, and
 * `auction` has no order at all -- in an auction the `draft_slot` on a pick is
 * a nomination position rather than a place in a queue.
 */
enum DraftType: string
{
    case Snake = 'snake';

    case Linear = 'linear';

    case Auction = 'auction';
}
