<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;

/**
 * A draft pick that has changed hands.
 *
 * Every ID here is a roster ID, including the two called `owner`. Sleeper
 * names them as though they referred to users; they do not. `rosterId` is the
 * team whose pick this originally was, `ownerId` is the team holding it now,
 * and `previousOwnerId` is who they got it from -- which is not necessarily
 * the originating team, since a pick can be traded on more than once.
 *
 * `season` is a string because it is a future season as often as the current
 * one: a dynasty league trades 2028 picks in 2026.
 *
 * The same shape turns up in three places with one key's difference between
 * them: `/league/{id}/traded_picks` carries neither extra ID, a trade's
 * `draft_picks` carries `league_id` (always null so far), and
 * `/draft/{id}/traded_picks` carries `draft_id` -- unquoted, unlike everywhere
 * else the same ID appears.
 */
final readonly class TradedPick implements ApiDataContract
{
    public function __construct(
        public string $season,
        public int $round,
        public int $rosterId,
        public int $ownerId,
        public ?int $previousOwnerId,
        public ?string $leagueId,
        public ?string $draftId,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            season: $payload->string('season'),
            round: $payload->int('round'),
            rosterId: $payload->int('roster_id'),
            ownerId: $payload->int('owner_id'),
            previousOwnerId: $payload->nullableInt('previous_owner_id'),
            leagueId: $payload->nullableString('league_id'),
            draftId: $payload->nullableString('draft_id'),
        );
    }
}
