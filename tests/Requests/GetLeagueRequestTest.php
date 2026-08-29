<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\Avatar;
use Fantasyphp\Sleeper\Data\Api\League;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Enums\LeagueStatus;
use Fantasyphp\Sleeper\Enums\SeasonType;
use Fantasyphp\Sleeper\Enums\Sport;
use Fantasyphp\Sleeper\Requests\GetLeagueRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetLeagueRequest::class)]
#[CoversClass(League::class)]
#[CoversClass(Avatar::class)]
#[CoversClass(LeagueStatus::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetLeagueRequestTest extends RequestTestCase
{
    /**
     * A completed public league from 2018. Frozen: nothing about it can change
     * any more, which is what makes it a fixture worth trusting.
     */
    private const string LEAGUE_ID = '289646328504385536';

    /**
     * A league that has not drafted yet, recorded because every other fixture
     * here comes from a finished season and none of them carry the states that
     * only exist before one starts.
     *
     * Unlike the 2018 league above, this one is live and will move on. If a
     * `composer fixtures:refresh` stops matching these assertions, the league
     * has drafted -- re-point this at another `pre_draft` league rather than
     * relaxing what is asserted.
     */
    private const string PRE_DRAFT_LEAGUE_ID = '1399461312144084992';

    #[Test]
    public function it_builds_the_league_path(): void
    {
        self::assertSame(
            'https://api.sleeper.app/v1/league/289646328504385536',
            $this->uriFor(new GetLeagueRequest(self::LEAGUE_ID)),
        );
    }

    #[Test]
    public function it_sends_no_query_string(): void
    {
        self::assertSame('', $this->queryFor(new GetLeagueRequest(self::LEAGUE_ID)));
        self::assertSame([], $this->queryParametersFor(new GetLeagueRequest(self::LEAGUE_ID)));
    }

    #[Test]
    public function a_recorded_response_hydrates_every_mapped_field(): void
    {
        $league = $this->dtoFrom(new GetLeagueRequest(self::LEAGUE_ID), 'league/detail');

        self::assertInstanceOf(League::class, $league);
        self::assertSame('289646328504385536', $league->leagueId);
        self::assertSame('Sleeper Friends League', $league->name);
        self::assertSame('complete', $league->status);
        self::assertSame('nfl', $league->sport);
        self::assertSame('2018', $league->season);
        self::assertSame('regular', $league->seasonType);
        self::assertSame(12, $league->totalRosters);
        self::assertSame('289646328508579840', $league->draftId);
        self::assertSame('198946952535085056', $league->previousLeagueId);
        self::assertNull($league->companyId);
        self::assertInstanceOf(Avatar::class, $league->avatar);
        self::assertSame('efaefa889ae24046a53265a3c71b8b64', $league->avatar->id);
        self::assertSame('SEASON 2 CHAMP', $league->metadata['trophy_winner_banner_text']);
    }

    #[Test]
    public function the_enum_accessors_narrow_the_raw_strings(): void
    {
        $league = $this->dtoFrom(new GetLeagueRequest(self::LEAGUE_ID), 'league/detail');

        self::assertInstanceOf(League::class, $league);
        self::assertSame(LeagueStatus::Complete, $league->state());
        self::assertSame(SeasonType::RegularSeason, $league->type());
        self::assertSame(Sport::Nfl, $league->sport());
    }

    /**
     * The bracket IDs are the one place Sleeper sends an ID unquoted. Read as
     * anything but a string they would be the odd ones out among two dozen
     * sibling identifiers.
     */
    #[Test]
    public function the_bracket_ids_arrive_unquoted_and_are_read_as_strings(): void
    {
        $league = $this->dtoFrom(new GetLeagueRequest(self::LEAGUE_ID), 'league/detail');

        self::assertInstanceOf(League::class, $league);
        self::assertSame('376175885998759936', $league->winnersBracketId);
        self::assertSame('376175886011342848', $league->losersBracketId);
    }

    /**
     * Neither map has a schema, so both are read as maps rather than frozen
     * into properties. What matters is that the keys survive and the values
     * come back as the right kind of number.
     */
    #[Test]
    public function settings_and_scoring_rules_are_read_as_maps(): void
    {
        $league = $this->dtoFrom(new GetLeagueRequest(self::LEAGUE_ID), 'league/detail');

        self::assertInstanceOf(League::class, $league);
        self::assertSame(12, $league->settings['num_teams']);
        self::assertSame(6, $league->settings['playoff_teams']);
        self::assertSame(14, $league->settings['playoff_week_start']);
        self::assertSame(100, $league->settings['waiver_budget']);
        self::assertSame(1.0, $league->scoringSettings['rec']);
        self::assertSame(6.0, $league->scoringSettings['pass_td']);
        self::assertEqualsWithDelta(0.04, $league->scoringSettings['pass_yd'], 0.0001);
    }

    /**
     * `roster_positions` is the whole slot list, bench included, so its length
     * is the squad size and not the starting lineup's.
     */
    #[Test]
    public function the_bench_slots_are_separable_from_the_starting_lineup(): void
    {
        $league = $this->dtoFrom(new GetLeagueRequest(self::LEAGUE_ID), 'league/detail');

        self::assertInstanceOf(League::class, $league);
        self::assertCount(15, $league->rosterPositions);
        self::assertSame(
            ['QB', 'RB', 'RB', 'WR', 'WR', 'TE', 'FLEX', 'FLEX', 'DEF'],
            $league->startingPositions(),
        );
    }

    #[Test]
    public function a_league_that_has_not_drafted_yet_hydrates(): void
    {
        $league = $this->dtoFrom(new GetLeagueRequest(self::PRE_DRAFT_LEAGUE_ID), 'league/preseason-detail');

        self::assertInstanceOf(League::class, $league);
        self::assertSame('pre_draft', $league->status);
        self::assertSame(LeagueStatus::PreDraft, $league->state());
        self::assertSame('2026', $league->season);
        self::assertSame(12, $league->totalRosters);
        // Assigned when the playoffs are configured, which has not happened.
        self::assertNull($league->winnersBracketId);
        self::assertNull($league->losersBracketId);
        // Set up but not run.
        self::assertNotNull($league->draftId);
    }

    /**
     * A league with no predecessor says so in two ways: `null` for most, and the
     * string `"0"` for a handful. Across 75 leagues it was 55 to 5. A caller
     * testing the raw property against null would try to fetch league `"0"`.
     */
    #[Test]
    public function both_spellings_of_no_predecessor_read_as_absent(): void
    {
        $league = $this->dtoFrom(new GetLeagueRequest(self::PRE_DRAFT_LEAGUE_ID), 'league/preseason-detail');

        self::assertInstanceOf(League::class, $league);
        self::assertNull($league->previousLeagueId);
        self::assertNull($league->previousSeason());

        self::assertNull($this->leagueWithPredecessor('0')->previousSeason());
        self::assertNull($this->leagueWithPredecessor(null)->previousSeason());
    }

    #[Test]
    public function a_league_that_continues_another_keeps_the_link(): void
    {
        $league = $this->dtoFrom(new GetLeagueRequest(self::LEAGUE_ID), 'league/detail');

        self::assertInstanceOf(League::class, $league);
        self::assertSame('198946952535085056', $league->previousSeason());
    }

    /**
     * The starting slots are re-indexed after the bench comes out, since a
     * league is free to interleave `BN` with the rest and Sleeper preserves
     * whatever order the commissioner set.
     */
    #[Test]
    public function the_starting_positions_come_back_as_a_list_not_a_sparse_array(): void
    {
        $league = League::fromPayload(Payload::of([
            'league_id' => '1',
            'name' => 'Test',
            'status' => 'complete',
            'sport' => 'nfl',
            'season' => '2026',
            'season_type' => 'regular',
            'total_rosters' => 10,
            'roster_positions' => ['BN', 'QB', 'BN', 'RB'],
        ]));

        self::assertSame(['QB', 'RB'], $league->startingPositions());
    }

    #[Test]
    public function an_unrecognised_status_reads_as_null_rather_than_throwing(): void
    {
        $league = League::fromPayload(Payload::of([
            'league_id' => '1',
            'name' => 'Test',
            'status' => 'hibernating',
            'sport' => 'quidditch',
            'season' => '2026',
            'season_type' => 'midseason',
            'total_rosters' => 10,
        ]));

        self::assertNull($league->state());
        self::assertNull($league->type());
        self::assertNull($league->sport());
        self::assertNull($league->avatar);
        self::assertSame([], $league->rosterPositions);
        self::assertSame([], $league->settings);
        self::assertSame([], $league->scoringSettings);
        self::assertSame([], $league->metadata);
        self::assertSame([], $league->startingPositions());
    }

    #[Test]
    public function the_endpoint_method_returns_a_typed_league(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([
            MockResponse::make([
                'league_id' => self::LEAGUE_ID,
                'name' => 'Sleeper Friends League',
                'status' => 'complete',
                'sport' => 'nfl',
                'season' => '2018',
                'season_type' => 'regular',
                'total_rosters' => 12,
            ]),
        ]);
        $connector->withMockClient($mockClient);

        $league = $connector->league(self::LEAGUE_ID);

        self::assertSame('Sleeper Friends League', $league->name);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame([], $pendingRequest->query()->all());
    }

    private function leagueWithPredecessor(?string $previousLeagueId): League
    {
        return League::fromPayload(Payload::of([
            'league_id' => '1',
            'name' => 'Test',
            'status' => 'pre_draft',
            'sport' => 'nfl',
            'season' => '2026',
            'season_type' => 'regular',
            'total_rosters' => 12,
            'previous_league_id' => $previousLeagueId,
        ]));
    }
}
