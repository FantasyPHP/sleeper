<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Enums\InjuryStatus;
use Fantasyphp\Sleeper\Enums\NflPosition;
use Fantasyphp\Sleeper\Enums\RosterStatus;

/**
 * One entry in the player map.
 *
 * Nearly everything is nullable because a team defense is a "player" here and
 * carries ten of the fifty-odd fields a human does: `"DET"` has a name, a
 * position and an active flag, and no age, college, height or external IDs at
 * all. The map also keeps every player who has ever been on a roster, so about
 * a quarter of it is `Inactive` and has no team.
 *
 * The numeric fields arrive as integers here and as *strings* in the snapshot
 * inside a draft pick. Same data, same API, two encodings -- see
 * `DraftedPlayer`.
 *
 * `height` is left as the raw string on purpose. Sleeper sends it as inches
 * (`"77"`) for some players and as feet and inches (`"6'5\""`) for others, with
 * `"0"` and `"0'0\""` as placeholders where it does not know. Parsing that into
 * a number would mean picking one interpretation and being wrong for the rest.
 *
 * `weight` is a numeric string, and `"0"` there is the same placeholder rather
 * than a real weight.
 */
final readonly class Player implements ApiDataContract
{
    /**
     * @param  list<string>  $fantasyPositions  the slots he is eligible for
     */
    public function __construct(
        public string $playerId,
        public string $firstName,
        public string $lastName,
        public ?string $fullName,
        public ?string $position,
        public array $fantasyPositions,
        public ?string $team,
        public ?int $number,
        public bool $active,
        public ?string $status,
        public ?string $injuryStatus,
        public ?string $injuryBodyPart,
        public ?string $injuryNotes,
        public ?int $age,
        public ?string $birthDate,
        public ?int $yearsExperience,
        public ?string $college,
        public ?string $height,
        public ?int $weight,
        public ?string $depthChartPosition,
        public ?int $depthChartOrder,
        public ?int $searchRank,
        public ExternalPlayerIds $externalIds,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            playerId: $payload->string('player_id'),
            firstName: $payload->string('first_name'),
            lastName: $payload->string('last_name'),
            fullName: $payload->nullableString('full_name'),
            position: $payload->nullableString('position'),
            fantasyPositions: $payload->strings('fantasy_positions'),
            team: $payload->nullableString('team'),
            number: $payload->nullableInt('number'),
            active: $payload->bool('active'),
            status: $payload->nullableString('status'),
            injuryStatus: $payload->nullableString('injury_status'),
            injuryBodyPart: $payload->nullableString('injury_body_part'),
            injuryNotes: $payload->nullableString('injury_notes'),
            age: $payload->nullableInt('age'),
            birthDate: $payload->nullableString('birth_date'),
            yearsExperience: $payload->nullableInt('years_exp'),
            college: $payload->nullableString('college'),
            height: self::blankToNull($payload->nullableString('height')),
            weight: $payload->nullableInt('weight'),
            depthChartPosition: $payload->nullableString('depth_chart_position'),
            depthChartOrder: $payload->nullableInt('depth_chart_order'),
            searchRank: $payload->nullableInt('search_rank'),
            externalIds: ExternalPlayerIds::fromPayload($payload),
        );
    }

    /**
     * Built from the two halves rather than read from `full_name`, which is
     * absent for a team defense and, across the whole player map, byte-identical
     * to the halves joined wherever it is present. Preferring it would add a
     * fallback that can never be reached.
     */
    public function name(): string
    {
        return $this->firstName.' '.$this->lastName;
    }

    /**
     * His real position, or null for one this client does not know.
     */
    public function slot(): ?NflPosition
    {
        return $this->position === null ? null : NflPosition::tryFrom($this->position);
    }

    /**
     * The slots he is eligible for, which need not include his real position:
     * an edge rusher listed at `DE` is eligible at `DL`, `LB` and `LEO`.
     *
     * Unrecognised values are dropped rather than throwing, so a slot Sleeper
     * adds later costs you that slot and not the player.
     *
     * @return list<NflPosition>
     */
    public function eligibleSlots(): array
    {
        return array_values(array_filter(array_map(
            NflPosition::tryFrom(...),
            $this->fantasyPositions,
        )));
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
     * A team defense, whose player ID is the team abbreviation rather than a
     * number. Worth being able to ask, since it is the case that breaks any
     * code assuming an ID is numeric.
     */
    public function isTeamDefense(): bool
    {
        return $this->slot() === NflPosition::TeamDefense;
    }

    private static function blankToNull(?string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
