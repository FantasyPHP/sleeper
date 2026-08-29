<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Enums;

/**
 * What kind of move a transaction was.
 *
 * A `waiver` and a `free_agent` look identical in the payload apart from this
 * field and the `settings.waiver_bid` that only the former carries: both are
 * an add, a drop, or one of each. The distinction is whether the player was
 * still inside the waiver period.
 */
enum TransactionType: string
{
    case Waiver = 'waiver';

    case FreeAgent = 'free_agent';

    case Trade = 'trade';

    case Commissioner = 'commissioner';
}
