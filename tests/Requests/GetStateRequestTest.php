<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\SportState;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Enums\SeasonType;
use Fantasyphp\Sleeper\Enums\Sport;
use Fantasyphp\Sleeper\Requests\GetStateRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetStateRequest::class)]
#[CoversClass(SportState::class)]
#[CoversClass(SeasonType::class)]
#[CoversClass(Sport::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetStateRequestTest extends RequestTestCase
{
    /**
     * @return iterable<string, array{Sport, string}>
     */
    public static function sports(): iterable
    {
        yield 'nfl' => [Sport::Nfl, 'https://api.sleeper.app/v1/state/nfl'];
        yield 'nba' => [Sport::Nba, 'https://api.sleeper.app/v1/state/nba'];
        yield 'mlb' => [Sport::Mlb, 'https://api.sleeper.app/v1/state/mlb'];
        yield 'nhl' => [Sport::Nhl, 'https://api.sleeper.app/v1/state/nhl'];
        yield 'wnba' => [Sport::Wnba, 'https://api.sleeper.app/v1/state/wnba'];
        yield 'lcs' => [Sport::Lcs, 'https://api.sleeper.app/v1/state/lcs'];
    }

    #[Test]
    #[DataProvider('sports')]
    public function it_builds_the_state_path_for_the_sport(Sport $sport, string $expected): void
    {
        self::assertSame($expected, $this->uriFor(new GetStateRequest($sport)));
    }

    #[Test]
    public function it_sends_no_query_string(): void
    {
        self::assertSame('', $this->queryFor(new GetStateRequest(Sport::Nfl)));
        self::assertSame([], $this->queryParametersFor(new GetStateRequest(Sport::Nfl)));
    }

    #[Test]
    public function a_recorded_response_hydrates_every_mapped_field(): void
    {
        $state = $this->dtoFrom(new GetStateRequest(Sport::Nfl), 'state/nfl');

        self::assertInstanceOf(SportState::class, $state);
        self::assertSame(3, $state->week);
        self::assertSame(3, $state->displayWeek);
        self::assertSame(0, $state->leg);
        self::assertSame('2026', $state->season);
        self::assertSame('2025', $state->previousSeason);
        self::assertSame('pre', $state->seasonType);
        self::assertSame('2026', $state->leagueSeason);
        self::assertSame('2026', $state->leagueCreateSeason);
        self::assertSame('2026-08-06', $state->seasonStartDate);
        self::assertTrue($state->seasonHasScores);
    }

    #[Test]
    public function the_season_type_is_available_as_an_enum(): void
    {
        $state = $this->dtoFrom(new GetStateRequest(Sport::Nfl), 'state/nfl');

        self::assertInstanceOf(SportState::class, $state);
        self::assertSame(SeasonType::PreSeason, $state->type());
    }

    /**
     * The esports state is the awkward one, and it is recorded precisely
     * because it is: the season is `"2022-summer"` rather than a year, the
     * regular season has no start date, and the response carries promo fields
     * no other sport sends. Every one of those would break a DTO modelled from
     * `/state/nfl` alone.
     */
    #[Test]
    public function a_season_that_is_not_a_year_still_hydrates(): void
    {
        $state = $this->dtoFrom(new GetStateRequest(Sport::Lcs), 'state/lcs');

        self::assertInstanceOf(SportState::class, $state);
        self::assertSame('2022-summer', $state->season);
        self::assertSame('2022-spring', $state->previousSeason);
        self::assertSame('2023-summer', $state->leagueSeason);
        self::assertNull($state->seasonStartDate);
        self::assertSame(SeasonType::OffSeason, $state->type());
    }

    /**
     * Fields NFL always sends are simply absent for other sports rather than
     * arriving null, so `has()`-style optionality is not enough -- the DTO has
     * to be nullable where the API is silent.
     */
    #[Test]
    public function a_sport_that_omits_half_the_fields_still_hydrates(): void
    {
        $state = $this->dtoFrom(new GetStateRequest(Sport::Wnba), 'state/wnba');

        self::assertInstanceOf(SportState::class, $state);
        self::assertNull($state->leg);
        self::assertNull($state->previousSeason);
        self::assertNull($state->displayWeek);
        self::assertNull($state->leagueSeason);
        self::assertNull($state->leagueCreateSeason);
        self::assertSame(SeasonType::RegularSeason, $state->type());
    }

    /**
     * The endpoint method is the only surface a consumer of this SDK touches,
     * so it has to arrive typed. `Response::dto()` would hand back `mixed` and
     * lose that at the call site.
     */
    #[Test]
    public function the_endpoint_method_returns_a_typed_state(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([
            MockResponse::make(['week' => 3, 'season' => '2026', 'season_type' => 'pre']),
        ]);
        $connector->withMockClient($mockClient);

        $state = $connector->state(Sport::Nfl);

        self::assertSame(3, $state->week);
        self::assertSame(SeasonType::PreSeason, $state->type());

        // Asked for nothing beyond the sport, so nothing beyond the sport was
        // sent. The endpoint method must not smuggle in defaults of its own.
        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame([], $pendingRequest->query()->all());
        self::assertSame('https://api.sleeper.app/v1/state/nfl', (string) $pendingRequest->getUri());
    }

    /**
     * `season_type` is read raw and only then narrowed, so a value Sleeper
     * invents next season leaves the rest of the state usable.
     */
    #[Test]
    public function an_unrecognised_season_type_reads_as_null_rather_than_throwing(): void
    {
        $state = SportState::fromPayload(Payload::of([
            'week' => 1,
            'season' => '2026',
            'season_type' => 'intergalactic',
        ]));

        self::assertSame('intergalactic', $state->seasonType);
        self::assertNull($state->type());
    }
}
