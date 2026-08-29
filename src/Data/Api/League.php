<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Enums\LeagueStatus;
use Fantasyphp\Sleeper\Enums\SeasonType;
use Fantasyphp\Sleeper\Enums\Sport;

/**
 * One league's configuration.
 *
 * `settings` and `scoringSettings` are left as flat maps on purpose. There is
 * no schema for either -- a league sends whichever of ~40 settings and ~90
 * scoring rules its format uses and omits the rest, and Sleeper adds new ones
 * without notice. Freezing them into properties would mean a DTO that throws
 * the first time somebody enables a rule this client has not heard of, so they
 * are read as maps and the keys are the API's own.
 *
 * Roughly a dozen chat fields (`last_message_id`, `last_author_avatar`,
 * `last_read_id` and friends) are not modelled. They are state for Sleeper's
 * own client, not facts about the league.
 *
 * `previousLeagueId` is what links a dynasty league's seasons together --
 * follow it back to walk a league's history. A league with no predecessor says
 * so in two different ways: across 75 leagues, 55 send `null` and 5 send the
 * string `"0"`. Testing the raw property against null gets the second group
 * wrong, so read it through `previousSeason()`.
 */
final readonly class League implements ApiDataContract
{
    /**
     * @param  list<string>  $rosterPositions  every slot including bench and flex
     * @param  array<array-key, int>  $settings
     * @param  array<array-key, float>  $scoringSettings
     * @param  array<array-key, string>  $metadata
     */
    public function __construct(
        public string $leagueId,
        public string $name,
        public string $status,
        public string $sport,
        public string $season,
        public string $seasonType,
        public int $totalRosters,
        public ?Avatar $avatar,
        public ?string $draftId,
        public ?string $previousLeagueId,
        public ?string $winnersBracketId,
        public ?string $losersBracketId,
        public ?string $companyId,
        public array $rosterPositions,
        public array $settings,
        public array $scoringSettings,
        public array $metadata,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        $avatar = $payload->nullableString('avatar');

        return new self(
            leagueId: $payload->string('league_id'),
            name: $payload->string('name'),
            status: $payload->string('status'),
            sport: $payload->string('sport'),
            season: $payload->string('season'),
            seasonType: $payload->string('season_type'),
            totalRosters: $payload->int('total_rosters'),
            avatar: $avatar === null ? null : new Avatar($avatar),
            draftId: $payload->nullableString('draft_id'),
            previousLeagueId: $payload->nullableString('previous_league_id'),
            // Sends these two unquoted where every other ID is a string, so
            // they are coerced back rather than typed differently.
            winnersBracketId: $payload->nullableString('bracket_id'),
            losersBracketId: $payload->nullableString('loser_bracket_id'),
            companyId: $payload->nullableString('company_id'),
            rosterPositions: $payload->strings('roster_positions'),
            settings: $payload->intMap('settings'),
            scoringSettings: $payload->floatMap('scoring_settings'),
            metadata: $payload->stringMap('metadata'),
        );
    }

    /**
     * The league's status as an enum, or null for one this client does not
     * know. Sleeper documents none of them, so treat an unknown as possible.
     */
    public function state(): ?LeagueStatus
    {
        return LeagueStatus::tryFrom($this->status);
    }

    public function type(): ?SeasonType
    {
        return SeasonType::tryFrom($this->seasonType);
    }

    public function sport(): ?Sport
    {
        return Sport::tryFrom($this->sport);
    }

    /**
     * The league this one continues, or null if it starts a fresh history.
     *
     * Folds the two spellings of "no predecessor" -- `null` and `"0"` -- into
     * one absence, so walking a dynasty league's seasons backwards terminates
     * on both rather than trying to fetch league `"0"`.
     */
    public function previousSeason(): ?string
    {
        return $this->previousLeagueId === '0' ? null : $this->previousLeagueId;
    }

    /**
     * The slots a lineup is actually set in, with the bench removed.
     *
     * `roster_positions` is the full slot list and includes `BN` for every
     * bench place, so its length is the squad size rather than the starting
     * lineup's. A `Matchup`'s `starters` lines up index-for-index with this,
     * not with the raw list.
     *
     * @return list<string>
     */
    public function startingPositions(): array
    {
        return array_values(array_filter(
            $this->rosterPositions,
            static fn (string $position): bool => $position !== 'BN',
        ));
    }
}
