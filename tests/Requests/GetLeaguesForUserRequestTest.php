<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\League;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Enums\Sport;
use Fantasyphp\Sleeper\Requests\GetLeaguesForUserRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetLeaguesForUserRequest::class)]
#[CoversClass(League::class)]
#[CoversClass(PayloadList::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetLeaguesForUserRequestTest extends RequestTestCase
{
    private const string USER_ID = '189140835533586432';

    #[Test]
    public function it_builds_the_user_leagues_path(): void
    {
        self::assertSame(
            'https://api.sleeper.app/v1/user/189140835533586432/leagues/nfl/2018',
            $this->uriFor(new GetLeaguesForUserRequest(self::USER_ID, Sport::Nfl, '2018')),
        );
    }

    #[Test]
    public function it_sends_no_query_string(): void
    {
        $request = new GetLeaguesForUserRequest(self::USER_ID, Sport::Nfl, '2018');

        self::assertSame('', $this->queryFor($request));
        self::assertSame([], $this->queryParametersFor($request));
    }

    #[Test]
    public function a_recorded_response_hydrates_a_list_of_leagues(): void
    {
        $leagues = $this->dtoFrom(
            new GetLeaguesForUserRequest(self::USER_ID, Sport::Nfl, '2018'),
            'league/for-user',
        );

        self::assertIsArray($leagues);
        self::assertCount(3, $leagues);

        $names = [];

        foreach ($leagues as $league) {
            self::assertInstanceOf(League::class, $league);
            self::assertSame('2018', $league->season);
            self::assertSame('nfl', $league->sport);
            $names[] = $league->name;
        }

        self::assertSame(['Dynasty Warriors', 'Comrades League', 'Sleeper Friends League'], $names);
    }

    /**
     * A bare array is what comes back, so there is no count or metadata to
     * cross-check against -- an account with no leagues that season is `[]`
     * and not an error.
     */
    #[Test]
    public function an_account_with_no_leagues_that_season_is_an_empty_list(): void
    {
        $connector = new SleeperConnector;
        $connector->withMockClient(new MockClient([MockResponse::make([])]));

        self::assertSame([], $connector->leaguesForUser(self::USER_ID, Sport::Nfl, '2030'));
    }

    #[Test]
    public function the_endpoint_method_returns_typed_leagues(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([
            MockResponse::make([[
                'league_id' => '289646328504385536',
                'name' => 'Sleeper Friends League',
                'status' => 'complete',
                'sport' => 'nfl',
                'season' => '2018',
                'season_type' => 'regular',
                'total_rosters' => 12,
            ]]),
        ]);
        $connector->withMockClient($mockClient);

        $leagues = $connector->leaguesForUser(self::USER_ID, Sport::Nfl, '2018');

        self::assertCount(1, $leagues);
        self::assertSame('Sleeper Friends League', $leagues[0]->name);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame(
            'https://api.sleeper.app/v1/user/189140835533586432/leagues/nfl/2018',
            (string) $pendingRequest->getUri(),
        );
    }
}
