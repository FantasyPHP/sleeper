<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;

/**
 * One selection made in a draft.
 *
 * `pickNo` counts across the whole draft, `round` and `draftSlot` locate it
 * within one. In a snake draft the slot alternates direction round by round,
 * so slot 1 is not always the first pick of the round.
 *
 * `pickedBy` is a user ID and `rosterId` is a team -- the two are not
 * interchangeable, and `pickedBy` is empty for a pick the system autodrafted.
 *
 * The `player` snapshot is what the metadata carries; `playerId` is the same ID
 * unquoted, and is the one to use against the player map.
 */
final readonly class DraftPick implements ApiDataContract
{
    public function __construct(
        public string $draftId,
        public int $pickNumber,
        public int $round,
        public int $draftSlot,
        public int $rosterId,
        public string $playerId,
        public ?string $pickedByUserId,
        public bool $isKeeper,
        public DraftedPlayer $player,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            draftId: $payload->string('draft_id'),
            pickNumber: $payload->int('pick_no'),
            round: $payload->int('round'),
            draftSlot: $payload->int('draft_slot'),
            rosterId: $payload->int('roster_id'),
            playerId: $payload->string('player_id'),
            pickedByUserId: self::blankToNull($payload->nullableString('picked_by')),
            // Null for every pick that isn't one, never false.
            isKeeper: $payload->bool('is_keeper'),
            player: DraftedPlayer::fromPayload($payload->object('metadata')),
        );
    }

    private static function blankToNull(?string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
