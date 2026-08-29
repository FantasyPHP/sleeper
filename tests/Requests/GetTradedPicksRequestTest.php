<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\TradedPick;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Requests\GetTradedPicksRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetTradedPicksRequest::class)]
#[CoversClass(TradedPick::class)]
#[CoversClass(PayloadList::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetTradedPicksRequestTest extends RequestTestCase
{
    private const string LEAGUE_ID = '289646328504385536';

    #[Test]
    public function it_builds_the_traded_picks_path(): void
    {
        self::assertSame(
            'https://api.sleeper.app/v1/league/289646328504385536/traded_picks',
            $this->uriFor(new GetTradedPicksRequest(self::LEAGUE_ID)),
        );
    }

    #[Test]
    public function it_sends_no_query_string(): void
    {
        self::assertSame('', $this->queryFor(new GetTradedPicksRequest(self::LEAGUE_ID)));
        self::assertSame([], $this->queryParametersFor(new GetTradedPicksRequest(self::LEAGUE_ID)));
    }

    /**
     * Every ID on a traded pick is a roster ID, including the two Sleeper names
     * as though they were users.
     */
    #[Test]
    public function a_recorded_response_hydrates_every_mapped_field(): void
    {
        $picks = $this->dtoFrom(new GetTradedPicksRequest(self::LEAGUE_ID), 'league/traded-picks');

        self::assertIsArray($picks);
        self::assertCount(4, $picks);

        $pick = $picks[0];

        self::assertInstanceOf(TradedPick::class, $pick);
        self::assertSame('2018', $pick->season);
        self::assertSame(6, $pick->round);
        self::assertSame(2, $pick->rosterId);
        self::assertSame(3, $pick->ownerId);
        self::assertSame(2, $pick->previousOwnerId);
        // The league route carries neither extra ID: `league_id` only turns up
        // on the copy nested inside a trade, `draft_id` only on the draft route.
        self::assertNull($pick->leagueId);
        self::assertNull($pick->draftId);
    }

    /**
     * The season is a string because it is a future season as often as the
     * current one -- a dynasty league trades picks years ahead.
     */
    #[Test]
    public function a_future_season_hydrates_as_a_string(): void
    {
        $pick = TradedPick::fromPayload(Payload::of([
            'season' => '2028',
            'round' => 1,
            'roster_id' => 3,
            'owner_id' => 1,
            'previous_owner_id' => null,
            'league_id' => null,
        ]));

        self::assertSame('2028', $pick->season);
        self::assertNull($pick->previousOwnerId);
        self::assertNull($pick->draftId);
    }

    #[Test]
    public function a_league_with_no_traded_picks_is_an_empty_list(): void
    {
        $connector = new SleeperConnector;
        $connector->withMockClient(new MockClient([MockResponse::make([])]));

        self::assertSame([], $connector->tradedPicks(self::LEAGUE_ID));
    }

    #[Test]
    public function the_endpoint_method_returns_typed_picks(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([
            MockResponse::make([[
                'season' => '2019',
                'round' => 1,
                'roster_id' => 3,
                'owner_id' => 1,
                'previous_owner_id' => 3,
            ]]),
        ]);
        $connector->withMockClient($mockClient);

        $picks = $connector->tradedPicks(self::LEAGUE_ID);

        self::assertCount(1, $picks);
        self::assertSame('2019', $picks[0]->season);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame([], $pendingRequest->query()->all());
    }
}
