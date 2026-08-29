<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;

/**
 * One team's squad and season record.
 *
 * Every player list is a list of *strings*, and those strings are not always
 * numeric: a team defense is its team abbreviation, so `"DET"` sits in the
 * same array as `"4035"`. Casting any of this to an integer loses the
 * defenses silently.
 *
 * `players`, `starters`, `reserve`, `taxi`, `keepers` and `coOwners` are
 * inconsistent about how they say "nothing": a 2018 league sends `null`, a 2026
 * one sends `[]`, sometimes within the same response. They are read as lists
 * that may be empty rather than as nullable lists, since an empty bench and an
 * absent bench are the same thing to a caller.
 *
 * `starters` has a third way of being empty. Before a draft, every slot is
 * filled with the literal player ID `"0"` -- a twelve-team league's rosters all
 * come back with ten `"0"`s rather than an empty lineup. Those are placeholders
 * and match no player; they are left in place because filtering them would mean
 * this DTO deciding that `"0"` can never be a real ID.
 *
 * `ownerId` is nullable because a league can carry an orphan team nobody has
 * claimed -- a freshly created league is mostly orphans until people join.
 */
final readonly class Roster implements ApiDataContract
{
    /**
     * @param  list<string>  $players  the whole squad, starters included
     * @param  list<string>  $starters  in the league's roster-position order
     * @param  list<string>  $reserve  injured reserve
     * @param  list<string>  $taxi  the dynasty taxi squad
     * @param  list<string>  $keepers
     * @param  list<string>  $coOwners
     * @param  array<array-key, string>  $metadata
     */
    public function __construct(
        public int $rosterId,
        public string $leagueId,
        public ?string $ownerId,
        public array $coOwners,
        public array $players,
        public array $starters,
        public array $reserve,
        public array $taxi,
        public array $keepers,
        public RosterSettings $settings,
        public array $metadata,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            rosterId: $payload->int('roster_id'),
            leagueId: $payload->string('league_id'),
            ownerId: $payload->nullableString('owner_id'),
            coOwners: $payload->strings('co_owners'),
            players: $payload->strings('players'),
            starters: $payload->strings('starters'),
            reserve: $payload->strings('reserve'),
            taxi: $payload->strings('taxi'),
            keepers: $payload->strings('keepers'),
            settings: RosterSettings::fromPayload($payload->object('settings')),
            metadata: $payload->stringMap('metadata'),
        );
    }

    /**
     * The squad with the starting lineup taken out.
     *
     * Sleeper sends no bench list; the docs say to deduce it, and this is that
     * deduction in one place rather than at every call site.
     *
     * @return list<string>
     */
    public function bench(): array
    {
        return array_values(array_diff($this->players, $this->starters));
    }
}
