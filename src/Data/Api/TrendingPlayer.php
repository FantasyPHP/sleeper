<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;

/**
 * A player and how many leagues added or dropped him in the lookback window.
 *
 * That is the whole payload -- an ID and a count, no name and no position. Two
 * of these carrying the same `playerId` from the add list and the drop list are
 * about the same person, and resolving either to a name means a separate call
 * to the player map.
 *
 * Sleeper asks for attribution when you publish this data.
 */
final readonly class TrendingPlayer implements ApiDataContract
{
    public function __construct(
        public string $playerId,
        public int $count,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            playerId: $payload->string('player_id'),
            count: $payload->int('count'),
        );
    }
}
