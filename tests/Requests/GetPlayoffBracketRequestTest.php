<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\BracketMatchup;
use Fantasyphp\Sleeper\Data\Api\BracketSlot;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Enums\BracketType;
use Fantasyphp\Sleeper\Requests\GetPlayoffBracketRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetPlayoffBracketRequest::class)]
#[CoversClass(BracketMatchup::class)]
#[CoversClass(BracketSlot::class)]
#[CoversClass(BracketType::class)]
#[CoversClass(PayloadList::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetPlayoffBracketRequestTest extends RequestTestCase
{
    private const string LEAGUE_ID = '289646328504385536';

    /**
     * A league whose playoffs have not been played. Live, so a
     * `fixtures:refresh` that stops matching means it has -- re-point this at
     * another unplayed league rather than relaxing the assertions.
     */
    private const string PRE_DRAFT_LEAGUE_ID = '1399461312144084992';

    #[Test]
    public function it_builds_the_winners_bracket_path(): void
    {
        self::assertSame(
            'https://api.sleeper.app/v1/league/289646328504385536/winners_bracket',
            $this->uriFor(new GetPlayoffBracketRequest(self::LEAGUE_ID, BracketType::Winners)),
        );
    }

    /**
     * The docs' `HTTP Request` line for this one reads `loses_bracket`. That
     * route does not exist -- it answers 404 with an HTML redirect page, not
     * even JSON. `losers_bracket` is what the docs' own curl example calls and
     * what the API actually serves, so this asserts the working spelling
     * rather than the printed one.
     */
    #[Test]
    public function the_losers_bracket_uses_the_route_that_works_not_the_documented_one(): void
    {
        self::assertSame(
            'https://api.sleeper.app/v1/league/289646328504385536/losers_bracket',
            $this->uriFor(new GetPlayoffBracketRequest(self::LEAGUE_ID, BracketType::Losers)),
        );
        self::assertStringNotContainsString('loses_bracket', $this->uriFor(
            new GetPlayoffBracketRequest(self::LEAGUE_ID, BracketType::Losers),
        ));
    }

    #[Test]
    public function it_sends_no_query_string(): void
    {
        $request = new GetPlayoffBracketRequest(self::LEAGUE_ID, BracketType::Winners);

        self::assertSame('', $this->queryFor($request));
        self::assertSame([], $this->queryParametersFor($request));
    }

    #[Test]
    public function a_recorded_response_hydrates_every_mapped_field(): void
    {
        $bracket = $this->bracket(BracketType::Winners, 'league/winners-bracket');

        self::assertCount(7, $bracket);

        $first = $bracket[0];

        self::assertSame(1, $first->matchId);
        self::assertSame(1, $first->round);
        self::assertSame(5, $first->team1RosterId);
        self::assertSame(1, $first->team2RosterId);
        self::assertSame(1, $first->winnerRosterId);
        self::assertSame(5, $first->loserRosterId);
        self::assertNull($first->placement);
        // A first-round team comes from the standings, not from another match.
        self::assertNull($first->team1From);
        self::assertNull($first->team2From);
    }

    /**
     * A completed bracket keeps its `_from` references alongside the filled-in
     * roster IDs, so it says both who played and how they got there.
     */
    #[Test]
    public function a_later_round_records_where_each_team_came_from(): void
    {
        $bracket = $this->bracket(BracketType::Winners, 'league/winners-bracket');

        $secondRound = $bracket[2];

        self::assertSame(3, $secondRound->matchId);
        self::assertSame(2, $secondRound->round);
        self::assertSame(1, $secondRound->team2RosterId);
        self::assertInstanceOf(BracketSlot::class, $secondRound->team2From);
        self::assertSame(1, $secondRound->team2From->matchId);
        self::assertTrue($secondRound->team2From->winner);
    }

    /**
     * `{"l": 1}` and `{"w": 1}` are the same shape with different meanings, so
     * the key that is present is the whole distinction.
     */
    #[Test]
    public function a_consolation_slot_takes_the_loser_of_an_earlier_match(): void
    {
        $bracket = $this->bracket(BracketType::Winners, 'league/winners-bracket');

        $fifthPlace = $bracket[4];

        self::assertSame(5, $fifthPlace->placement);
        self::assertInstanceOf(BracketSlot::class, $fifthPlace->team1From);
        self::assertSame(1, $fifthPlace->team1From->matchId);
        self::assertFalse($fifthPlace->team1From->winner);
    }

    /**
     * Only the matches that settle a final position carry `p`.
     */
    #[Test]
    public function the_championship_is_the_match_placed_first(): void
    {
        $bracket = $this->bracket(BracketType::Winners, 'league/winners-bracket');

        $final = array_values(array_filter(
            $bracket,
            static fn (BracketMatchup $matchup): bool => $matchup->placement === 1,
        ));

        self::assertCount(1, $final);
        self::assertSame(3, $final[0]->round);
        self::assertSame(6, $final[0]->winnerRosterId);
    }

    #[Test]
    public function the_losers_bracket_hydrates_the_same_way(): void
    {
        $bracket = $this->bracket(BracketType::Losers, 'league/losers-bracket');

        self::assertCount(7, $bracket);
        self::assertSame(1, $bracket[0]->round);
    }

    /**
     * A bracket exists before a ball is thrown. Every match is seeded, no match
     * has a result, and the dependent slots carry only their references -- the
     * shape this DTO was written for, now against a real response rather than a
     * hand-written one.
     */
    #[Test]
    public function an_unplayed_bracket_is_all_seeding_and_no_results(): void
    {
        $bracket = $this->bracketFor(
            self::PRE_DRAFT_LEAGUE_ID,
            BracketType::Winners,
            'league/preseason-winners-bracket',
        );

        self::assertCount(9, $bracket);

        foreach ($bracket as $matchup) {
            self::assertNull($matchup->winnerRosterId);
            self::assertNull($matchup->loserRosterId);
        }

        // Round one is seeded straight from the standings.
        self::assertSame(1, $bracket[0]->round);
        self::assertSame(9, $bracket[0]->team1RosterId);
        self::assertSame(8, $bracket[0]->team2RosterId);
        self::assertNull($bracket[0]->team1From);

        // A later round knows only where its teams will come from.
        $final = $bracket[6];

        self::assertSame(1, $final->placement);
        self::assertNull($final->team1RosterId);
        self::assertInstanceOf(BracketSlot::class, $final->team1From);
        self::assertSame(4, $final->team1From->matchId);
        self::assertTrue($final->team1From->winner);
    }

    /**
     * A bye: one seed is already through and only the other slot is pending, so
     * a match can carry a roster ID and a reference at the same time.
     */
    #[Test]
    public function a_seeded_team_can_await_an_opponent(): void
    {
        $bracket = $this->bracketFor(
            self::PRE_DRAFT_LEAGUE_ID,
            BracketType::Winners,
            'league/preseason-winners-bracket',
        );

        $bye = $bracket[3];

        self::assertSame(2, $bye->round);
        self::assertSame(12, $bye->team1RosterId);
        self::assertNull($bye->team1From);
        self::assertNull($bye->team2RosterId);
        self::assertInstanceOf(BracketSlot::class, $bye->team2From);
        self::assertSame(1, $bye->team2From->matchId);
    }

    /**
     * Before the playoffs start, both slots of a dependent match are null and
     * the `_from` objects are all there is to go on.
     */
    #[Test]
    public function an_unplayed_match_carries_only_its_references(): void
    {
        $matchup = BracketMatchup::fromPayload(Payload::of([
            'r' => 2,
            'm' => 5,
            't1' => null,
            't2' => null,
            't1_from' => ['l' => 1],
            't2_from' => ['l' => 2],
            'w' => null,
            'l' => null,
            'p' => 5,
        ]));

        self::assertNull($matchup->team1RosterId);
        self::assertNull($matchup->winnerRosterId);
        self::assertNull($matchup->loserRosterId);
        self::assertInstanceOf(BracketSlot::class, $matchup->team1From);
        self::assertFalse($matchup->team1From->winner);
        self::assertInstanceOf(BracketSlot::class, $matchup->team2From);
        self::assertSame(2, $matchup->team2From->matchId);
    }

    #[Test]
    public function both_endpoint_methods_return_typed_brackets(): void
    {
        $row = [['r' => 1, 'm' => 1, 't1' => 5, 't2' => 1, 'w' => 1, 'l' => 5]];

        $connector = new SleeperConnector;
        $mockClient = new MockClient([MockResponse::make($row), MockResponse::make($row)]);
        $connector->withMockClient($mockClient);

        self::assertCount(1, $connector->winnersBracket(self::LEAGUE_ID));

        $winners = $mockClient->getLastPendingRequest();

        self::assertNotNull($winners);
        self::assertStringEndsWith('/winners_bracket', (string) $winners->getUri());

        self::assertCount(1, $connector->losersBracket(self::LEAGUE_ID));

        $losers = $mockClient->getLastPendingRequest();

        self::assertNotNull($losers);
        self::assertStringEndsWith('/losers_bracket', (string) $losers->getUri());
    }

    /**
     * @return list<BracketMatchup>
     */
    private function bracket(BracketType $type, string $fixture): array
    {
        return $this->bracketFor(self::LEAGUE_ID, $type, $fixture);
    }

    /**
     * @return list<BracketMatchup>
     */
    private function bracketFor(string $leagueId, BracketType $type, string $fixture): array
    {
        $bracket = $this->dtoFrom(new GetPlayoffBracketRequest($leagueId, $type), $fixture);

        self::assertIsArray($bracket);

        $typed = [];

        foreach ($bracket as $matchup) {
            self::assertInstanceOf(BracketMatchup::class, $matchup);
            $typed[] = $matchup;
        }

        return $typed;
    }
}
