<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\Draft;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Requests\GetDraftRequest;
use Fantasyphp\Sleeper\Requests\GetLeagueDraftsRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetLeagueDraftsRequest::class)]
#[CoversClass(GetDraftRequest::class)]
#[CoversClass(Draft::class)]
#[CoversClass(PayloadList::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetLeagueDraftsRequestTest extends RequestTestCase
{
    /**
     * A dynasty league, which is the kind that holds more than one draft.
     */
    private const string LEAGUE_ID = '384109584450625536';

    #[Test]
    public function it_builds_the_league_drafts_path(): void
    {
        self::assertSame(
            'https://api.sleeper.app/v1/league/384109584450625536/drafts',
            $this->uriFor(new GetLeagueDraftsRequest(self::LEAGUE_ID)),
        );
    }

    #[Test]
    public function it_sends_no_query_string(): void
    {
        self::assertSame('', $this->queryFor(new GetLeagueDraftsRequest(self::LEAGUE_ID)));
        self::assertSame([], $this->queryParametersFor(new GetLeagueDraftsRequest(self::LEAGUE_ID)));
    }

    /**
     * A `League` carries one `draftId`, which is only the latest. A dynasty
     * league drafts every season, and this route is the only way to see the
     * rest.
     */
    #[Test]
    public function a_league_can_have_held_more_than_one_draft(): void
    {
        $drafts = $this->dtoFrom(new GetLeagueDraftsRequest(self::LEAGUE_ID), 'draft/for-league');

        self::assertIsArray($drafts);
        self::assertCount(2, $drafts);

        foreach ($drafts as $draft) {
            self::assertInstanceOf(Draft::class, $draft);
            self::assertSame(self::LEAGUE_ID, $draft->leagueId);
        }
    }

    /**
     * The list route's draft is a strict subset of the detail route's: it omits
     * `slot_to_roster_id` entirely, for a finished draft as much as a pending
     * one. Anything needing the slot map has to ask for the draft by ID, or it
     * will read an empty map and conclude the draft was never seeded.
     */
    #[Test]
    public function the_list_route_omits_the_slot_map_the_detail_route_sends(): void
    {
        $drafts = $this->dtoFrom(new GetLeagueDraftsRequest(self::LEAGUE_ID), 'draft/for-league');

        self::assertIsArray($drafts);

        foreach ($drafts as $draft) {
            self::assertInstanceOf(Draft::class, $draft);
            self::assertSame([], $draft->slotToRosterId);
        }

        $detail = $this->dtoFrom(new GetDraftRequest('418854418453114880'), 'draft/detail');

        self::assertInstanceOf(Draft::class, $detail);
        self::assertCount(12, $detail->slotToRosterId);
    }

    #[Test]
    public function the_endpoint_method_returns_typed_drafts(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([
            MockResponse::make([[
                'draft_id' => '418854418453114880',
                'league_id' => self::LEAGUE_ID,
                'sport' => 'nfl',
                'season' => '2019',
                'season_type' => 'regular',
                'status' => 'complete',
                'type' => 'linear',
            ]]),
        ]);
        $connector->withMockClient($mockClient);

        $drafts = $connector->leagueDrafts(self::LEAGUE_ID);

        self::assertCount(1, $drafts);
        self::assertSame('418854418453114880', $drafts[0]->draftId);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame([], $pendingRequest->query()->all());
    }
}
