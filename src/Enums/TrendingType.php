<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Enums;

/**
 * Which direction of waiver activity the trending list measures.
 *
 * A type Sleeper does not recognise is not an error: it answers `200` with an
 * empty array, so a typo in a raw request would look like a quiet day rather
 * than a mistake. That is why this is an enum.
 */
enum TrendingType: string
{
    case Add = 'add';

    case Drop = 'drop';
}
