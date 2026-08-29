<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\DraftedPlayer;
use Fantasyphp\Sleeper\Data\Api\DraftPick;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Enums\InjuryStatus;
use Fantasyphp\Sleeper\Enums\NflPosition;
use Fantasyphp\Sleeper\Enums\RosterStatus;
use Fantasyphp\Sleeper\Requests\GetDraftPicksRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetDraftPicksRequest::class)]
#[CoversClass(DraftPick::class)]
#[CoversClass(DraftedPlayer::class)]
#[CoversClass(NflPosition::class)]
#[CoversClass(RosterStatus::class)]
#[CoversClass(InjuryStatus::class)]
#[CoversClass(PayloadList::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetDraftPicksRequestTest extends RequestTestCase
{
    private const string DRAFT_ID = '418854418453114880';

    /**
     * A draft that was superseded before anybody picked in it.
     */
    private const string UNSTARTED_DRAFT_ID = '384109584450625537';

    #[Test]
    public function it_builds_the_draft_picks_path(): void
    {
        self::assertSame(
            'https://api.sleeper.app/v1/draft/418854418453114880/picks',
            $this->uriFor(new GetDraftPicksRequest(self::DRAFT_ID)),
        );
    }

    #[Test]
    public function it_sends_no_query_string(): void
    {
        self::assertSame('', $this->queryFor(new GetDraftPicksRequest(self::DRAFT_ID)));
        self::assertSame([], $this->queryParametersFor(new GetDraftPicksRequest(self::DRAFT_ID)));
    }

    #[Test]
    public function a_recorded_response_hydrates_every_mapped_field(): void
    {
        $picks = $this->picks(self::DRAFT_ID, 'draft/picks');

        self::assertCount(36, $picks);

        $first = $picks[0];

        self::assertSame(self::DRAFT_ID, $first->draftId);
        self::assertSame(1, $first->pickNumber);
        self::assertSame(1, $first->round);
        self::assertSame(1, $first->draftSlot);
        self::assertSame(8, $first->rosterId);
        self::assertSame('5850', $first->playerId);
        self::assertSame('84732593924620288', $first->pickedByUserId);
        self::assertFalse($first->isKeeper);
    }

    /**
     * The snapshot inside the pick is what the player looked like in 2019, not
     * what he looks like now. Every value in it arrives as a string, including
     * the shirt number and the years of experience.
     */
    #[Test]
    public function the_pick_carries_a_snapshot_of_the_player_as_he_was(): void
    {
        $player = $this->picks(self::DRAFT_ID, 'draft/picks')[0]->player;

        self::assertSame('5850', $player->playerId);
        self::assertSame('Josh', $player->firstName);
        self::assertSame('Jacobs', $player->lastName);
        self::assertSame('Josh Jacobs', $player->name());
        self::assertSame('RB', $player->position);
        self::assertSame('OAK', $player->team);
        self::assertSame('Active', $player->status);
        self::assertSame(8, $player->number);
        self::assertSame(0, $player->yearsExperience);
        self::assertSame(NflPosition::RunningBack, $player->slot());
        self::assertSame(RosterStatus::Active, $player->rosterStatus());
    }

    /**
     * The one place in the API that says "nothing to report" with an empty
     * string rather than null. Left alone it would read as a designation whose
     * name happens to be blank.
     */
    #[Test]
    public function an_empty_injury_status_reads_as_no_injury(): void
    {
        $player = $this->picks(self::DRAFT_ID, 'draft/picks')[0]->player;

        self::assertNull($player->injuryStatus);
        self::assertNull($player->injury());
    }

    #[Test]
    public function an_injured_player_carries_a_designation(): void
    {
        $player = DraftedPlayer::fromPayload(Payload::of([
            'player_id' => '5850',
            'first_name' => 'Josh',
            'last_name' => 'Jacobs',
            'injury_status' => 'Questionable',
        ]));

        self::assertSame('Questionable', $player->injuryStatus);
        self::assertSame(InjuryStatus::Questionable, $player->injury());
    }

    #[Test]
    public function a_snapshot_missing_everything_but_a_name_still_hydrates(): void
    {
        $player = DraftedPlayer::fromPayload(Payload::of([
            'player_id' => 'DET',
            'first_name' => 'Detroit',
            'last_name' => 'Lions',
        ]));

        self::assertSame('DET', $player->playerId);
        self::assertNull($player->position);
        self::assertNull($player->team);
        self::assertNull($player->status);
        self::assertNull($player->number);
        self::assertNull($player->yearsExperience);
        self::assertNull($player->slot());
        self::assertNull($player->rosterStatus());
        self::assertNull($player->injury());
    }

    /**
     * A draft nobody started answers `[]`, not a 404, so an empty list here
     * means "not yet" rather than "no such draft".
     */
    #[Test]
    public function a_draft_nobody_started_has_no_picks(): void
    {
        self::assertSame(
            [],
            $this->dtoFrom(new GetDraftPicksRequest(self::UNSTARTED_DRAFT_ID), 'draft/no-picks'),
        );
    }

    /**
     * An autodrafted pick has no user behind it, and Sleeper sends an empty
     * string rather than null for that too.
     */
    #[Test]
    public function an_autodrafted_pick_has_nobody_behind_it(): void
    {
        $pick = DraftPick::fromPayload(Payload::of([
            'draft_id' => self::DRAFT_ID,
            'pick_no' => 4,
            'round' => 1,
            'draft_slot' => 4,
            'roster_id' => 2,
            'player_id' => '5850',
            'picked_by' => '',
            'is_keeper' => null,
            'metadata' => ['player_id' => '5850', 'first_name' => 'Josh', 'last_name' => 'Jacobs'],
        ]));

        self::assertNull($pick->pickedByUserId);
        self::assertFalse($pick->isKeeper);
    }

    #[Test]
    public function the_endpoint_method_returns_typed_picks(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([
            MockResponse::make([[
                'draft_id' => self::DRAFT_ID,
                'pick_no' => 1,
                'round' => 1,
                'draft_slot' => 1,
                'roster_id' => 8,
                'player_id' => '5850',
                'picked_by' => '84732593924620288',
                'is_keeper' => true,
                'metadata' => ['player_id' => '5850', 'first_name' => 'Josh', 'last_name' => 'Jacobs'],
            ]]),
        ]);
        $connector->withMockClient($mockClient);

        $picks = $connector->draftPicks(self::DRAFT_ID);

        self::assertCount(1, $picks);
        self::assertTrue($picks[0]->isKeeper);
        self::assertSame('Josh Jacobs', $picks[0]->player->name());

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame([], $pendingRequest->query()->all());
    }

    /**
     * @return list<DraftPick>
     */
    private function picks(string $draftId, string $fixture): array
    {
        $picks = $this->dtoFrom(new GetDraftPicksRequest($draftId), $fixture);

        self::assertIsArray($picks);

        $typed = [];

        foreach ($picks as $pick) {
            self::assertInstanceOf(DraftPick::class, $pick);
            $typed[] = $pick;
        }

        return $typed;
    }
}
