<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\Draft;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Enums\Sport;
use Fantasyphp\Sleeper\Requests\GetDraftsForUserRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetDraftsForUserRequest::class)]
#[CoversClass(Draft::class)]
#[CoversClass(PayloadList::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetDraftsForUserRequestTest extends RequestTestCase
{
    private const string USER_ID = '189140835533586432';

    #[Test]
    public function it_builds_the_user_drafts_path(): void
    {
        self::assertSame(
            'https://api.sleeper.app/v1/user/189140835533586432/drafts/nfl/2018',
            $this->uriFor(new GetDraftsForUserRequest(self::USER_ID, Sport::Nfl, '2018')),
        );
    }

    #[Test]
    public function it_sends_no_query_string(): void
    {
        $request = new GetDraftsForUserRequest(self::USER_ID, Sport::Nfl, '2018');

        self::assertSame('', $this->queryFor($request));
        self::assertSame([], $this->queryParametersFor($request));
    }

    #[Test]
    public function a_recorded_response_hydrates_a_list_of_drafts(): void
    {
        $drafts = $this->dtoFrom(
            new GetDraftsForUserRequest(self::USER_ID, Sport::Nfl, '2018'),
            'draft/for-user',
        );

        self::assertIsArray($drafts);
        self::assertCount(2, $drafts);

        $ids = [];

        foreach ($drafts as $draft) {
            self::assertInstanceOf(Draft::class, $draft);
            self::assertSame('2018', $draft->season);
            self::assertSame('nfl', $draft->sport);
            $ids[] = $draft->draftId;
        }

        self::assertSame(['330813448747253761', '289646328508579840'], $ids);
    }

    #[Test]
    public function an_account_that_drafted_nowhere_that_season_is_an_empty_list(): void
    {
        $connector = new SleeperConnector;
        $connector->withMockClient(new MockClient([MockResponse::make([])]));

        self::assertSame([], $connector->draftsForUser(self::USER_ID, Sport::Nfl, '2030'));
    }

    #[Test]
    public function the_endpoint_method_returns_typed_drafts(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([
            MockResponse::make([[
                'draft_id' => '289646328508579840',
                'sport' => 'nfl',
                'season' => '2018',
                'season_type' => 'regular',
                'status' => 'complete',
                'type' => 'snake',
            ]]),
        ]);
        $connector->withMockClient($mockClient);

        $drafts = $connector->draftsForUser(self::USER_ID, Sport::Nfl, '2018');

        self::assertCount(1, $drafts);
        self::assertSame('289646328508579840', $drafts[0]->draftId);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame(
            'https://api.sleeper.app/v1/user/189140835533586432/drafts/nfl/2018',
            (string) $pendingRequest->getUri(),
        );
    }
}
