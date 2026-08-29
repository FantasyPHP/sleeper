<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;

/**
 * One match in a playoff bracket.
 *
 * Everything here is a roster ID, never a user ID. A bracket knows about teams,
 * not about the people who own them.
 *
 * Before a match is played, `team1RosterId` and `team2RosterId` are null for
 * any slot that depends on an earlier result, and `team1From`/`team2From` say
 * which result. Afterwards Sleeper fills the roster IDs in and leaves the
 * `_from` objects in place, so a completed bracket carries both the outcome
 * and how each team got there.
 *
 * `placement` appears only on the matches that decide a final position -- `1`
 * on the championship, `3` on the third-place game -- and is absent on the
 * rounds leading up to them.
 */
final readonly class BracketMatchup implements ApiDataContract
{
    public function __construct(
        public int $matchId,
        public int $round,
        public ?int $team1RosterId,
        public ?int $team2RosterId,
        public ?BracketSlot $team1From,
        public ?BracketSlot $team2From,
        public ?int $winnerRosterId,
        public ?int $loserRosterId,
        public ?int $placement,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        $team1From = $payload->nullableObject('t1_from');
        $team2From = $payload->nullableObject('t2_from');

        return new self(
            matchId: $payload->int('m'),
            round: $payload->int('r'),
            team1RosterId: $payload->nullableInt('t1'),
            team2RosterId: $payload->nullableInt('t2'),
            team1From: $team1From instanceof Payload ? BracketSlot::fromPayload($team1From) : null,
            team2From: $team2From instanceof Payload ? BracketSlot::fromPayload($team2From) : null,
            winnerRosterId: $payload->nullableInt('w'),
            loserRosterId: $payload->nullableInt('l'),
            placement: $payload->nullableInt('p'),
        );
    }
}
