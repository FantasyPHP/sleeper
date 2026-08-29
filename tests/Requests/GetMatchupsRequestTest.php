<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\Matchup;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Requests\GetMatchupsRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetMatchupsRequest::class)]
#[CoversClass(Matchup::class)]
#[CoversClass(PayloadList::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetMatchupsRequestTest extends RequestTestCase
{
    private const string LEAGUE_ID = '289646328504385536';

    #[Test]
    public function it_builds_the_matchups_path_for_the_week(): void
    {
        self::assertSame(
            'https://api.sleeper.app/v1/league/289646328504385536/matchups/1',
            $this->uriFor(new GetMatchupsRequest(self::LEAGUE_ID, 1)),
        );
        self::assertSame(
            'https://api.sleeper.app/v1/league/289646328504385536/matchups/14',
            $this->uriFor(new GetMatchupsRequest(self::LEAGUE_ID, 14)),
        );
    }

    #[Test]
    public function it_sends_no_query_string(): void
    {
        self::assertSame('', $this->queryFor(new GetMatchupsRequest(self::LEAGUE_ID, 1)));
        self::assertSame([], $this->queryParametersFor(new GetMatchupsRequest(self::LEAGUE_ID, 1)));
    }

    #[Test]
    public function a_recorded_response_hydrates_every_mapped_field(): void
    {
        $matchup = $this->firstMatchupOfWeek(1, 'league/matchups');

        self::assertSame(1, $matchup->rosterId);
        self::assertSame(2, $matchup->matchupId);
        self::assertSame(148.04, $matchup->points);
        self::assertNull($matchup->customPoints);
        self::assertCount(9, $matchup->starters);
        self::assertCount(9, $matchup->starterPoints);
        self::assertCount(15, $matchup->players);
        self::assertCount(15, $matchup->playerPoints);
        self::assertSame(11.0, $matchup->playerPoints['PHI']);
    }

    /**
     * `starters_points` is a positional array rather than a map, so it lines up
     * index-for-index with `starters` -- and both line up with the league's
     * starting roster positions.
     */
    #[Test]
    public function starter_points_line_up_with_the_starters(): void
    {
        $matchup = $this->firstMatchupOfWeek(1, 'league/matchups');

        self::assertSame('421', $matchup->starters[0]);
        self::assertSame(10.04, $matchup->starterPoints[0]);
        self::assertSame($matchup->playerPoints['421'], $matchup->starterPoints[0]);
    }

    /**
     * Weekly points come as one number, unlike a roster's season totals, which
     * Sleeper splits in half. Nothing here needs recombining.
     */
    #[Test]
    public function weekly_points_are_not_split_into_halves(): void
    {
        $matchup = $this->firstMatchupOfWeek(1, 'league/matchups');

        self::assertSame($matchup->points, $matchup->score());
    }

    /**
     * The playoff case, which is why `matchupId` is nullable. Every team still
     * gets a row in week 14; the ones knocked out have points and a lineup and
     * nobody to play.
     */
    #[Test]
    public function an_eliminated_team_still_gets_a_row_with_no_opponent(): void
    {
        $matchups = $this->matchups(14, 'league/matchups-playoff-week');

        $withoutOpponent = array_filter(
            $matchups,
            static fn (Matchup $matchup): bool => $matchup->matchupId === null,
        );

        self::assertNotSame([], $withoutOpponent);

        foreach ($withoutOpponent as $matchup) {
            self::assertNotSame([], $matchup->starters);
        }
    }

    /**
     * A commissioner override replaces the computed score. When it is set it is
     * the number that decides the week, so reading `points` alone would report
     * the wrong result.
     */
    #[Test]
    public function a_commissioner_override_wins_over_the_computed_points(): void
    {
        $matchup = Matchup::fromPayload(Payload::of([
            'roster_id' => 1,
            'matchup_id' => 1,
            'points' => 101.5,
            'custom_points' => 120.0,
        ]));

        self::assertSame(101.5, $matchup->points);
        self::assertSame(120.0, $matchup->customPoints);
        self::assertSame(120.0, $matchup->score());
    }

    /**
     * A week that has not been played sends `points: null` rather than zero.
     */
    #[Test]
    public function an_unplayed_week_scores_nothing(): void
    {
        $matchup = Matchup::fromPayload(Payload::of([
            'roster_id' => 1,
            'matchup_id' => 1,
            'points' => null,
        ]));

        self::assertSame(0.0, $matchup->points);
        self::assertSame(0.0, $matchup->score());
        self::assertSame([], $matchup->starters);
        self::assertSame([], $matchup->starterPoints);
        self::assertSame([], $matchup->playerPoints);
    }

    #[Test]
    public function the_endpoint_method_returns_typed_matchups(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([
            MockResponse::make([[
                'roster_id' => 1,
                'matchup_id' => 2,
                'points' => 148.04,
                'starters' => ['421'],
                'starters_points' => [10.04],
                'players' => ['421'],
                'players_points' => ['421' => 10.04],
            ]]),
        ]);
        $connector->withMockClient($mockClient);

        $matchups = $connector->matchups(self::LEAGUE_ID, 1);

        self::assertCount(1, $matchups);
        self::assertSame(148.04, $matchups[0]->score());

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame([], $pendingRequest->query()->all());
    }

    /**
     * @return list<Matchup>
     */
    private function matchups(int $week, string $fixture): array
    {
        $matchups = $this->dtoFrom(new GetMatchupsRequest(self::LEAGUE_ID, $week), $fixture);

        self::assertIsArray($matchups);
        self::assertCount(12, $matchups);

        $typed = [];

        foreach ($matchups as $matchup) {
            self::assertInstanceOf(Matchup::class, $matchup);
            $typed[] = $matchup;
        }

        return $typed;
    }

    private function firstMatchupOfWeek(int $week, string $fixture): Matchup
    {
        return $this->matchups($week, $fixture)[0];
    }
}
