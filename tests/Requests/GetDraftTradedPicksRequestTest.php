<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\TradedPick;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Requests\GetDraftTradedPicksRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetDraftTradedPicksRequest::class)]
#[CoversClass(TradedPick::class)]
#[CoversClass(PayloadList::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetDraftTradedPicksRequestTest extends RequestTestCase
{
    private const string DRAFT_ID = '418854418453114880';

    #[Test]
    public function it_builds_the_draft_traded_picks_path(): void
    {
        self::assertSame(
            'https://api.sleeper.app/v1/draft/418854418453114880/traded_picks',
            $this->uriFor(new GetDraftTradedPicksRequest(self::DRAFT_ID)),
        );
    }

    #[Test]
    public function it_sends_no_query_string(): void
    {
        $request = new GetDraftTradedPicksRequest(self::DRAFT_ID);

        self::assertSame('', $this->queryFor($request));
        self::assertSame([], $this->queryParametersFor($request));
    }

    /**
     * The same `TradedPick` shape as the league route, plus a `draft_id` that
     * arrives unquoted where the identical ID is a string everywhere else.
     */
    #[Test]
    public function a_recorded_response_hydrates_every_mapped_field(): void
    {
        $picks = $this->dtoFrom(new GetDraftTradedPicksRequest(self::DRAFT_ID), 'draft/traded-picks');

        self::assertIsArray($picks);
        self::assertCount(12, $picks);

        $pick = $picks[0];

        self::assertInstanceOf(TradedPick::class, $pick);
        self::assertSame('2019', $pick->season);
        self::assertSame(1, $pick->round);
        self::assertSame(1, $pick->rosterId);
        self::assertSame(11, $pick->ownerId);
        self::assertSame(1, $pick->previousOwnerId);
        self::assertSame(self::DRAFT_ID, $pick->draftId);
        // Carried only by the copy nested inside a trade.
        self::assertNull($pick->leagueId);
    }

    #[Test]
    public function a_draft_with_no_traded_picks_is_an_empty_list(): void
    {
        $connector = new SleeperConnector;
        $connector->withMockClient(new MockClient([MockResponse::make([])]));

        self::assertSame([], $connector->draftTradedPicks(self::DRAFT_ID));
    }

    #[Test]
    public function the_endpoint_method_returns_typed_picks(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([
            MockResponse::make([[
                'season' => '2019',
                'round' => 1,
                'roster_id' => 1,
                'owner_id' => 11,
                'previous_owner_id' => 1,
                'draft_id' => self::DRAFT_ID,
            ]]),
        ]);
        $connector->withMockClient($mockClient);

        $picks = $connector->draftTradedPicks(self::DRAFT_ID);

        self::assertCount(1, $picks);
        self::assertSame(self::DRAFT_ID, $picks[0]->draftId);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame([], $pendingRequest->query()->all());
    }
}
