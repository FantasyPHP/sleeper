<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\Roster;
use Fantasyphp\Sleeper\Data\Api\RosterSettings;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Requests\GetRostersRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetRostersRequest::class)]
#[CoversClass(Roster::class)]
#[CoversClass(RosterSettings::class)]
#[CoversClass(PayloadList::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetRostersRequestTest extends RequestTestCase
{
    private const string LEAGUE_ID = '289646328504385536';

    /**
     * A league that has not drafted yet. Live, so a `fixtures:refresh` that
     * stops matching means it has drafted -- re-point this at another
     * `pre_draft` league rather than relaxing the assertions.
     */
    private const string PRE_DRAFT_LEAGUE_ID = '1399461312144084992';

    #[Test]
    public function it_builds_the_rosters_path(): void
    {
        self::assertSame(
            'https://api.sleeper.app/v1/league/289646328504385536/rosters',
            $this->uriFor(new GetRostersRequest(self::LEAGUE_ID)),
        );
    }

    #[Test]
    public function it_sends_no_query_string(): void
    {
        self::assertSame('', $this->queryFor(new GetRostersRequest(self::LEAGUE_ID)));
        self::assertSame([], $this->queryParametersFor(new GetRostersRequest(self::LEAGUE_ID)));
    }

    #[Test]
    public function a_recorded_response_hydrates_every_mapped_field(): void
    {
        $roster = $this->firstRoster();

        self::assertSame(1, $roster->rosterId);
        self::assertSame(self::LEAGUE_ID, $roster->leagueId);
        self::assertSame('189140835533586432', $roster->ownerId);
        self::assertSame([], $roster->coOwners);
        self::assertSame([], $roster->reserve);
        self::assertSame([], $roster->taxi);
        self::assertSame([], $roster->keepers);
        self::assertCount(15, $roster->players);
        self::assertCount(9, $roster->starters);
        self::assertSame('16 FAAB FOR TRADE', $roster->metadata['p_nick_2118']);
    }

    /**
     * The rule that breaks a naive port: a team defense's player ID is its
     * team abbreviation. Casting these to integers would turn `"CLE"` into `0`
     * and lose the defense without any error.
     */
    #[Test]
    public function a_team_defense_keeps_its_non_numeric_player_id(): void
    {
        $roster = $this->firstRoster();

        self::assertContains('CLE', $roster->players);
        self::assertContains('CLE', $roster->starters);
        self::assertContains('4881', $roster->players);
    }

    /**
     * Sleeper stores 1776.06 as `fpts: 1776` plus `fpts_decimal: 6`. The
     * fractional half is hundredths, so a 6 is 0.06 -- reading it as tenths
     * would inflate the total by an order of magnitude in the fractional part.
     */
    #[Test]
    public function split_points_totals_are_recombined_into_one_number(): void
    {
        $roster = $this->firstRoster();

        self::assertSame(1776.06, $roster->settings->pointsFor);
        self::assertSame(1695.36, $roster->settings->pointsAgainst);
        self::assertSame(2004.42, $roster->settings->maximumPoints);
    }

    #[Test]
    public function the_season_record_hydrates(): void
    {
        $roster = $this->firstRoster();

        self::assertSame(7, $roster->settings->wins);
        self::assertSame(6, $roster->settings->losses);
        self::assertSame(0, $roster->settings->ties);
        self::assertSame(4, $roster->settings->waiverPosition);
        self::assertSame(100, $roster->settings->waiverBudgetUsed);
        self::assertSame(0, $roster->settings->totalMoves);
    }

    /**
     * A total that lands on a whole number omits the decimal half entirely
     * rather than sending zero, and a league that does not compute a maximum
     * omits `ppts` altogether.
     */
    #[Test]
    public function a_missing_decimal_half_counts_as_zero_and_a_missing_total_as_absent(): void
    {
        $settings = RosterSettings::fromPayload(Payload::of([
            'wins' => 0,
            'losses' => 0,
            'ties' => 0,
            'fpts' => 120,
            'fpts_against' => 0,
        ]));

        self::assertSame(120.0, $settings->pointsFor);
        self::assertSame(0.0, $settings->pointsAgainst);
        self::assertNull($settings->maximumPoints);
        self::assertSame(0, $settings->waiverPosition);
        self::assertSame(0, $settings->waiverBudgetUsed);
        self::assertSame(0, $settings->totalMoves);
    }

    /**
     * Everything the recorded league happens to leave at zero -- it has no
     * ties and nobody made a move in the recorded week -- so a default that
     * quietly overwrote the real value would look identical there.
     */
    #[Test]
    public function a_non_zero_record_is_read_rather_than_defaulted(): void
    {
        $settings = RosterSettings::fromPayload(Payload::of([
            'wins' => 9,
            'losses' => 3,
            'ties' => 2,
            'total_moves' => 41,
            'waiver_position' => 6,
            'waiver_budget_used' => 73,
            'fpts' => 1500,
            'fpts_decimal' => 5,
        ]));

        self::assertSame(9, $settings->wins);
        self::assertSame(3, $settings->losses);
        self::assertSame(2, $settings->ties);
        self::assertSame(41, $settings->totalMoves);
        self::assertSame(6, $settings->waiverPosition);
        self::assertSame(73, $settings->waiverBudgetUsed);
        self::assertSame(1500.05, $settings->pointsFor);
    }

    /**
     * A brand-new league sends no settings at all rather than zeroed ones.
     */
    #[Test]
    public function an_empty_settings_object_hydrates_to_a_blank_record(): void
    {
        $settings = RosterSettings::fromPayload(Payload::of([]));

        self::assertSame(0, $settings->wins);
        self::assertSame(0, $settings->losses);
        self::assertSame(0, $settings->ties);
        self::assertSame(0.0, $settings->pointsFor);
        self::assertSame(0.0, $settings->pointsAgainst);
        self::assertNull($settings->maximumPoints);
        self::assertSame(0, $settings->waiverPosition);
        self::assertSame(0, $settings->waiverBudgetUsed);
        self::assertSame(0, $settings->totalMoves);
    }

    /**
     * Sleeper sends no bench list. The docs say to deduce it by removing the
     * starters, and the starters are always a subset of `players`.
     */
    #[Test]
    public function the_bench_is_the_squad_minus_the_starters(): void
    {
        $roster = $this->firstRoster();

        self::assertCount(6, $roster->bench());
        self::assertSame([], array_intersect($roster->bench(), $roster->starters));
        self::assertSame([], array_diff($roster->bench(), $roster->players));
    }

    /**
     * The bench is re-indexed after the starters come out. Without that, a
     * starter sitting in the middle of `players` leaves a hole in the keys and
     * the result stops being a list.
     */
    #[Test]
    public function the_bench_comes_back_as_a_list_not_a_sparse_array(): void
    {
        $roster = Roster::fromPayload(Payload::of([
            'roster_id' => 1,
            'league_id' => self::LEAGUE_ID,
            'players' => ['1', '2', '3', '4'],
            'starters' => ['2', '4'],
            'settings' => [],
        ]));

        self::assertSame(['1', '3'], $roster->bench());
    }

    /**
     * An orphan team has no owner, and every player list arrives as `null`
     * rather than as an empty array.
     */
    #[Test]
    public function an_unclaimed_roster_hydrates_with_nothing_in_it(): void
    {
        $roster = Roster::fromPayload(Payload::of([
            'roster_id' => 9,
            'league_id' => self::LEAGUE_ID,
            'owner_id' => null,
            'players' => null,
            'starters' => null,
            'reserve' => null,
            'taxi' => null,
            'keepers' => null,
            'co_owners' => null,
            'metadata' => null,
            'settings' => [],
        ]));

        self::assertNull($roster->ownerId);
        self::assertSame([], $roster->players);
        self::assertSame([], $roster->starters);
        self::assertSame([], $roster->bench());
        self::assertSame([], $roster->metadata);
    }

    /**
     * Before a draft, a lineup is not empty -- every slot holds the literal
     * player ID `"0"`. Ten of them here, one per starting position. They match
     * no player, and they are left in place rather than filtered, since
     * stripping them would mean deciding `"0"` can never be a real ID.
     */
    #[Test]
    public function an_undrafted_lineup_is_filled_with_placeholder_ids(): void
    {
        $roster = $this->preDraftRosters()[0];

        self::assertSame([], $roster->players);
        self::assertSame(array_fill(0, 10, '0'), $roster->starters);
        // Nothing to bench when nothing has been drafted.
        self::assertSame([], $roster->bench());
    }

    /**
     * Nine of these twelve teams have nobody in them. Modelled from the start,
     * but until now only ever exercised against a hand-written payload.
     */
    #[Test]
    public function a_freshly_created_league_is_mostly_orphan_teams(): void
    {
        $unclaimed = array_filter(
            $this->preDraftRosters(),
            static fn (Roster $roster): bool => $roster->ownerId === null,
        );

        self::assertCount(9, $unclaimed);
    }

    /**
     * The same fields that arrive as `null` in the 2018 league arrive as `[]`
     * here. Both read as an empty list, which is the point of not typing them
     * as nullable.
     */
    #[Test]
    public function empty_lists_and_absent_lists_hydrate_the_same_way(): void
    {
        $preDraft = $this->preDraftRosters()[0];
        $complete = $this->firstRoster();

        self::assertSame([], $preDraft->keepers);
        self::assertSame([], $preDraft->reserve);
        self::assertSame([], $preDraft->taxi);
        self::assertSame([], $preDraft->coOwners);

        self::assertSame($complete->keepers, $preDraft->keepers);
        self::assertSame($complete->reserve, $preDraft->reserve);
    }

    /**
     * A league that has played nothing still reports a full settings block, so
     * the zeroes below are real rather than defaults this client invented.
     */
    #[Test]
    public function an_unplayed_season_scores_nothing(): void
    {
        $settings = $this->preDraftRosters()[0]->settings;

        self::assertSame(0, $settings->wins);
        self::assertSame(0.0, $settings->pointsFor);
        self::assertSame(0.0, $settings->pointsAgainst);
        // Not every league computes a perfect-lineup score; this one does not.
        self::assertNull($settings->maximumPoints);
    }

    #[Test]
    public function the_endpoint_method_returns_typed_rosters(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([
            MockResponse::make([[
                'roster_id' => 1,
                'league_id' => self::LEAGUE_ID,
                'owner_id' => '1',
                'players' => ['DET'],
                'starters' => ['DET'],
                'settings' => ['fpts' => 100, 'fpts_decimal' => 5],
            ]]),
        ]);
        $connector->withMockClient($mockClient);

        $rosters = $connector->rosters(self::LEAGUE_ID);

        self::assertCount(1, $rosters);
        self::assertSame(100.05, $rosters[0]->settings->pointsFor);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame([], $pendingRequest->query()->all());
    }

    /**
     * @return list<Roster>
     */
    private function preDraftRosters(): array
    {
        $rosters = $this->dtoFrom(
            new GetRostersRequest(self::PRE_DRAFT_LEAGUE_ID),
            'league/preseason-rosters',
        );

        self::assertIsArray($rosters);
        self::assertCount(12, $rosters);

        $typed = [];

        foreach ($rosters as $roster) {
            self::assertInstanceOf(Roster::class, $roster);
            $typed[] = $roster;
        }

        return $typed;
    }

    private function firstRoster(): Roster
    {
        $rosters = $this->dtoFrom(new GetRostersRequest(self::LEAGUE_ID), 'league/rosters');

        self::assertIsArray($rosters);
        self::assertCount(12, $rosters);
        self::assertInstanceOf(Roster::class, $rosters[0]);

        return $rosters[0];
    }
}
