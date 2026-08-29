<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Enums\SeasonType;

/**
 * Where a sport is right now: which week it is, which season, and whether that
 * season has started.
 *
 * Almost everything is nullable, and that is not defensive padding -- the same
 * endpoint returns a genuinely different set of keys per sport. `/state/nfl`
 * sends ten fields; `/state/wnba` sends five, with no `leg`, no
 * `previous_season` and no `display_week`. `/state/nba` adds a
 * `league_create_season_start_date` no other sport has, and `/state/lcs` adds
 * three `promo_*` fields.
 *
 * `season` is a string rather than an int because it is not always a year:
 * `/state/lcs` answers `"2022-summer"`. And `previous_season` arrives quoted
 * for NFL and unquoted for MLB, which is why it is read through the payload's
 * coercing string reader rather than trusted.
 *
 * `season_start_date` is the start of the *regular* season, and it is null for
 * a sport whose next season has not been scheduled yet.
 */
final readonly class SportState implements ApiDataContract
{
    public function __construct(
        public int $week,
        public ?int $displayWeek,
        public ?int $leg,
        public string $season,
        public ?string $previousSeason,
        public string $seasonType,
        public ?string $leagueSeason,
        public ?string $leagueCreateSeason,
        public ?string $seasonStartDate,
        public bool $seasonHasScores,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            week: $payload->int('week'),
            displayWeek: $payload->nullableInt('display_week'),
            leg: $payload->nullableInt('leg'),
            season: $payload->string('season'),
            previousSeason: $payload->nullableString('previous_season'),
            seasonType: $payload->string('season_type'),
            leagueSeason: $payload->nullableString('league_season'),
            leagueCreateSeason: $payload->nullableString('league_create_season'),
            seasonStartDate: $payload->nullableString('season_start_date'),
            seasonHasScores: $payload->bool('season_has_scores'),
        );
    }

    /**
     * The season type as an enum, or null if Sleeper reports one this SDK does
     * not know. It won't throw: an unrecognised phase of the season shouldn't
     * cost you the week number next to it.
     */
    public function type(): ?SeasonType
    {
        return SeasonType::tryFrom($this->seasonType);
    }
}
