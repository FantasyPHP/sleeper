<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\TrendingPlayer;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Enums\Sport;
use Fantasyphp\Sleeper\Enums\TrendingType;
use Fantasyphp\Sleeper\Requests\GetTrendingPlayersRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetTrendingPlayersRequest::class)]
#[CoversClass(TrendingPlayer::class)]
#[CoversClass(TrendingType::class)]
#[CoversClass(PayloadList::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetTrendingPlayersRequestTest extends RequestTestCase
{
    /**
     * @return iterable<string, array{TrendingType, string}>
     */
    public static function directions(): iterable
    {
        yield 'adds' => [TrendingType::Add, 'https://api.sleeper.app/v1/players/nfl/trending/add'];
        yield 'drops' => [TrendingType::Drop, 'https://api.sleeper.app/v1/players/nfl/trending/drop'];
    }

    #[Test]
    #[DataProvider('directions')]
    public function it_builds_the_trending_path_for_the_direction(TrendingType $type, string $expected): void
    {
        self::assertSame($expected, $this->uriFor(new GetTrendingPlayersRequest(Sport::Nfl, $type)));
    }

    /**
     * Sleeper's own defaults are 24 hours and 25 players. Leaving the
     * parameters off keeps those defaults theirs rather than pinning this
     * client's copy of them.
     */
    #[Test]
    public function it_registers_no_empty_parameters(): void
    {
        $request = new GetTrendingPlayersRequest(Sport::Nfl, TrendingType::Add);

        self::assertSame('', $this->queryFor($request));
        self::assertSame([], $this->queryParametersFor($request));
    }

    #[Test]
    public function it_sends_every_supported_filter(): void
    {
        $request = new GetTrendingPlayersRequest(Sport::Nfl, TrendingType::Add, lookbackHours: 48, limit: 5);

        self::assertSame('lookback_hours=48&limit=5', $this->queryFor($request));
    }

    #[Test]
    public function each_filter_can_be_sent_on_its_own(): void
    {
        self::assertSame(
            ['lookback_hours' => 48],
            $this->queryParametersFor(new GetTrendingPlayersRequest(Sport::Nfl, TrendingType::Add, lookbackHours: 48)),
        );

        self::assertSame(
            ['limit' => 5],
            $this->queryParametersFor(new GetTrendingPlayersRequest(Sport::Nfl, TrendingType::Add, limit: 5)),
        );
    }

    /**
     * An ID and a count is the whole payload -- no name, no position. Putting a
     * name to one of these means a second call to the player map.
     */
    #[Test]
    public function a_recorded_response_hydrates_every_mapped_field(): void
    {
        $trending = $this->dtoFrom(
            new GetTrendingPlayersRequest(Sport::Nfl, TrendingType::Add, limit: 5),
            'players/trending-add',
        );

        self::assertIsArray($trending);
        self::assertCount(5, $trending);

        foreach ($trending as $player) {
            self::assertInstanceOf(TrendingPlayer::class, $player);
            self::assertNotSame('', $player->playerId);
            self::assertGreaterThan(0, $player->count);
        }
    }

    /**
     * The list comes back most-added first, which is the only ordering
     * guarantee worth relying on here.
     */
    #[Test]
    public function the_list_is_ordered_by_how_many_leagues_moved_the_player(): void
    {
        $trending = $this->dtoFrom(
            new GetTrendingPlayersRequest(Sport::Nfl, TrendingType::Add, limit: 5),
            'players/trending-add',
        );

        self::assertIsArray($trending);

        $counts = [];

        foreach ($trending as $player) {
            self::assertInstanceOf(TrendingPlayer::class, $player);
            $counts[] = $player->count;
        }

        $sorted = $counts;
        rsort($sorted);

        self::assertSame($sorted, $counts);
    }

    /**
     * A direction Sleeper does not recognise answers `200 []` rather than an
     * error, which is why the direction is an enum -- a typo in a raw request
     * would look like a quiet day.
     */
    #[Test]
    public function an_empty_window_is_an_empty_list(): void
    {
        $connector = new SleeperConnector;
        $connector->withMockClient(new MockClient([MockResponse::make([])]));

        self::assertSame([], $connector->trendingPlayers(Sport::Nfl, TrendingType::Drop));
    }

    #[Test]
    public function the_endpoint_method_returns_typed_players(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([
            MockResponse::make([['count' => 121608, 'player_id' => '13264']]),
        ]);
        $connector->withMockClient($mockClient);

        $trending = $connector->trendingPlayers(Sport::Nfl, TrendingType::Add, lookbackHours: 48, limit: 1);

        self::assertCount(1, $trending);
        self::assertSame('13264', $trending[0]->playerId);
        self::assertSame(121608, $trending[0]->count);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame(['lookback_hours' => 48, 'limit' => 1], $pendingRequest->query()->all());
    }
}
