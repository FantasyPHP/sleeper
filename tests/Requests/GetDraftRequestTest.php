<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\Draft;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Enums\DraftStatus;
use Fantasyphp\Sleeper\Enums\DraftType;
use Fantasyphp\Sleeper\Enums\SeasonType;
use Fantasyphp\Sleeper\Enums\Sport;
use Fantasyphp\Sleeper\Requests\GetDraftRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetDraftRequest::class)]
#[CoversClass(Draft::class)]
#[CoversClass(DraftStatus::class)]
#[CoversClass(DraftType::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetDraftRequestTest extends RequestTestCase
{
    /**
     * A completed three-round rookie draft from a dynasty league. Small, and
     * the only kind that has traded picks to look at.
     */
    private const string DRAFT_ID = '418854418453114880';

    /**
     * An auction draft nobody has started. Live, so a `fixtures:refresh` that
     * stops matching means it has run -- re-point this at another `pre_draft`
     * draft rather than relaxing the assertions.
     */
    private const string PRE_DRAFT_DRAFT_ID = '1399461313431785472';

    #[Test]
    public function it_builds_the_draft_path(): void
    {
        self::assertSame(
            'https://api.sleeper.app/v1/draft/418854418453114880',
            $this->uriFor(new GetDraftRequest(self::DRAFT_ID)),
        );
    }

    #[Test]
    public function it_sends_no_query_string(): void
    {
        self::assertSame('', $this->queryFor(new GetDraftRequest(self::DRAFT_ID)));
        self::assertSame([], $this->queryParametersFor(new GetDraftRequest(self::DRAFT_ID)));
    }

    #[Test]
    public function a_recorded_response_hydrates_every_mapped_field(): void
    {
        $draft = $this->draft();

        self::assertSame(self::DRAFT_ID, $draft->draftId);
        self::assertSame('384109584450625536', $draft->leagueId);
        self::assertSame('nfl', $draft->sport);
        self::assertSame('2019', $draft->season);
        self::assertSame('regular', $draft->seasonType);
        self::assertSame('complete', $draft->status);
        self::assertSame('linear', $draft->type);
        self::assertSame(1554225184584, $draft->created);
        self::assertSame(1557023400000, $draft->startTime);
        self::assertSame(1558302719500, $draft->lastPicked);
        self::assertCount(3, $draft->creatorUserIds);
        self::assertSame('Dynasty Warriors', $draft->metadata['name']);
        self::assertSame('dynasty', $draft->metadata['scoring_type']);
        self::assertSame(3, $draft->settings['rounds']);
        self::assertSame(12, $draft->settings['teams']);
    }

    #[Test]
    public function the_enum_accessors_narrow_the_raw_strings(): void
    {
        $draft = $this->draft();

        self::assertSame(DraftStatus::Complete, $draft->state());
        self::assertSame(DraftType::Linear, $draft->order());
        self::assertSame(SeasonType::RegularSeason, $draft->type());
        self::assertSame(Sport::Nfl, $draft->sport());
    }

    /**
     * Two maps into the same draft, keyed differently: one by user, one by
     * slot. Going from a slot to the person who drafted from it needs both.
     */
    #[Test]
    public function the_draft_order_and_the_slot_map_are_keyed_differently(): void
    {
        $draft = $this->draft();

        self::assertCount(12, $draft->draftOrder);
        self::assertCount(12, $draft->slotToRosterId);
        // Keyed by user ID, valued by slot.
        self::assertSame(1, $draft->draftOrder['84732593924620288']);
        // Keyed by slot, valued by roster ID.
        self::assertSame(8, $draft->slotToRosterId[1]);
    }

    /**
     * The start time is milliseconds, like every other timestamp Sleeper sends.
     */
    #[Test]
    public function the_start_time_is_converted_from_milliseconds(): void
    {
        $startsAt = $this->draft()->startsAt();

        self::assertNotNull($startsAt);
        self::assertSame(1557023400, $startsAt->getTimestamp());
        self::assertSame('2019-05-05', $startsAt->format('Y-m-d'));
    }

    #[Test]
    public function a_draft_nobody_has_scheduled_has_no_start_time(): void
    {
        $draft = Draft::fromPayload(Payload::of([
            'draft_id' => '1',
            'sport' => 'nfl',
            'season' => '2026',
            'season_type' => 'regular',
            'status' => 'pre_draft',
            'type' => 'auction',
        ]));

        self::assertNull($draft->startTime);
        self::assertNull($draft->startsAt());
        self::assertNull($draft->lastPicked);
        self::assertNull($draft->leagueId);
        self::assertSame(0, $draft->created);
        self::assertSame([], $draft->creatorUserIds);
        self::assertSame([], $draft->draftOrder);
        self::assertSame([], $draft->slotToRosterId);
        self::assertSame([], $draft->settings);
        self::assertSame([], $draft->metadata);
        self::assertSame(DraftType::Auction, $draft->order());
        self::assertSame(DraftStatus::PreDraft, $draft->state());
    }

    /**
     * The state the completed fixture cannot show: an auction that has not run.
     * `draft_order` is `null` and `slot_to_roster_id` is missing altogether,
     * because neither exists until the order is set -- and in an auction the
     * order never means what it means in a snake draft anyway.
     */
    #[Test]
    public function a_draft_nobody_has_started_hydrates(): void
    {
        $draft = $this->preDraft();

        self::assertSame(DraftStatus::PreDraft, $draft->state());
        self::assertSame(DraftType::Auction, $draft->order());
        self::assertSame('2026', $draft->season);
        self::assertSame([], $draft->draftOrder);
        self::assertNull($draft->lastPicked);
        // Assigned before the order is drawn, so this one is already full.
        self::assertCount(12, $draft->slotToRosterId);
        // Scheduled, just not run.
        self::assertNotNull($draft->startsAt());
    }

    /**
     * An auction's settings carry keys a snake draft has no use for, which is
     * the reason `settings` is a map rather than a fixed set of properties.
     */
    #[Test]
    public function an_auction_carries_settings_a_snake_draft_does_not(): void
    {
        $settings = $this->preDraft()->settings;

        self::assertSame(200, $settings['budget']);
        self::assertSame(60, $settings['nomination_timer']);
        self::assertSame(15, $settings['rounds']);
        self::assertArrayNotHasKey('budget', $this->draft()->settings);
    }

    #[Test]
    public function an_unrecognised_type_or_status_reads_as_null(): void
    {
        $draft = Draft::fromPayload(Payload::of([
            'draft_id' => '1',
            'sport' => 'quidditch',
            'season' => '2026',
            'season_type' => 'midseason',
            'status' => 'hibernating',
            'type' => 'lottery',
        ]));

        self::assertNull($draft->state());
        self::assertNull($draft->order());
        self::assertNull($draft->type());
        self::assertNull($draft->sport());
    }

    #[Test]
    public function the_endpoint_method_returns_a_typed_draft(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([
            MockResponse::make([
                'draft_id' => self::DRAFT_ID,
                'sport' => 'nfl',
                'season' => '2019',
                'season_type' => 'regular',
                'status' => 'complete',
                'type' => 'linear',
            ]),
        ]);
        $connector->withMockClient($mockClient);

        $draft = $connector->draft(self::DRAFT_ID);

        self::assertSame(self::DRAFT_ID, $draft->draftId);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame([], $pendingRequest->query()->all());
    }

    private function draft(): Draft
    {
        return $this->hydrate(self::DRAFT_ID, 'draft/detail');
    }

    private function preDraft(): Draft
    {
        return $this->hydrate(self::PRE_DRAFT_DRAFT_ID, 'draft/preseason-detail');
    }

    private function hydrate(string $draftId, string $fixture): Draft
    {
        $draft = $this->dtoFrom(new GetDraftRequest($draftId), $fixture);

        self::assertInstanceOf(Draft::class, $draft);

        return $draft;
    }
}
