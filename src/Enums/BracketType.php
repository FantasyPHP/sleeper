<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Enums;

/**
 * Which end of the standings a playoff bracket covers.
 *
 * The path segments are here rather than inline in the request because one of
 * them is documented wrong. The docs' `HTTP Request` line reads
 * `/league/<league_id>/loses_bracket`; that route does not exist and answers
 * 404 with an HTML redirect page. The working route is `losers_bracket`, which
 * is what the docs' own curl example calls.
 */
enum BracketType: string
{
    case Winners = 'winners_bracket';

    case Losers = 'losers_bracket';
}
