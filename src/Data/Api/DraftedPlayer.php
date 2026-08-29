<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Enums\InjuryStatus;
use Fantasyphp\Sleeper\Enums\NflPosition;
use Fantasyphp\Sleeper\Enums\RosterStatus;

/**
 * The snapshot of a player carried inside a draft pick.
 *
 * Denormalised on purpose: it is what the player looked like when the pick was
 * made, so a draft from 2019 still renders without a second call. Do not read
 * it as current -- the team and the injury status are years stale on an old
 * draft.
 *
 * Every value in it is a string, including the ones that are obviously numbers.
 * `number`, `years_exp` and `news_updated` all arrive quoted, and `player_id`
 * arrives quoted here while the pick's own copy of it does not.
 *
 * `injury_status` is an empty string rather than null when there is nothing to
 * report, which is the only place in the API that does that.
 */
final readonly class DraftedPlayer implements ApiDataContract
{
    public function __construct(
        public string $playerId,
        public string $firstName,
        public string $lastName,
        public ?string $position,
        public ?string $team,
        public ?string $status,
        public ?string $injuryStatus,
        public ?int $number,
        public ?int $yearsExperience,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            playerId: $payload->string('player_id'),
            firstName: $payload->string('first_name'),
            lastName: $payload->string('last_name'),
            position: $payload->nullableString('position'),
            team: $payload->nullableString('team'),
            status: $payload->nullableString('status'),
            injuryStatus: self::blankToNull($payload->nullableString('injury_status')),
            number: $payload->nullableInt('number'),
            yearsExperience: $payload->nullableInt('years_exp'),
        );
    }

    public function name(): string
    {
        return $this->firstName.' '.$this->lastName;
    }

    public function slot(): ?NflPosition
    {
        return $this->position === null ? null : NflPosition::tryFrom($this->position);
    }

    public function rosterStatus(): ?RosterStatus
    {
        return $this->status === null ? null : RosterStatus::tryFrom($this->status);
    }

    public function injury(): ?InjuryStatus
    {
        return $this->injuryStatus === null ? null : InjuryStatus::tryFrom($this->injuryStatus);
    }

    /**
     * An empty string here means "nothing to report", not "the empty
     * designation", so it is folded into the same absence as a missing key.
     */
    private static function blankToNull(?string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
