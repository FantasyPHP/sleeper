<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;

/**
 * Where a team in a bracket match comes from.
 *
 * The payload is a one-key object: `{"w": 1}` reads "the winner of match 1",
 * `{"l": 1}` reads "the loser of match 1". Which key is present is the whole
 * meaning, so it is turned into a flag here rather than left as two nullable
 * integers only one of which is ever set.
 *
 * Present only for rounds after the first, since a first-round team comes from
 * the standings rather than from another match.
 */
final readonly class BracketSlot implements ApiDataContract
{
    private function __construct(
        public int $matchId,
        public bool $winner,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        $winner = $payload->nullableInt('w');

        if ($winner !== null) {
            return new self($winner, true);
        }

        return new self($payload->int('l'), false);
    }
}
