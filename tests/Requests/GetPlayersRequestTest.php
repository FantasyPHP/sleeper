<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\ExternalPlayerIds;
use Fantasyphp\Sleeper\Data\Api\Player;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Enums\InjuryStatus;
use Fantasyphp\Sleeper\Enums\NflPosition;
use Fantasyphp\Sleeper\Enums\RosterStatus;
use Fantasyphp\Sleeper\Enums\Sport;
use Fantasyphp\Sleeper\Requests\GetPlayersRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetPlayersRequest::class)]
#[CoversClass(Player::class)]
#[CoversClass(ExternalPlayerIds::class)]
#[CoversClass(NflPosition::class)]
#[CoversClass(RosterStatus::class)]
#[CoversClass(InjuryStatus::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetPlayersRequestTest extends RequestTestCase
{
    #[Test]
    public function it_builds_the_players_path_for_the_sport(): void
    {
        self::assertSame(
            'https://api.sleeper.app/v1/players/nfl',
            $this->uriFor(new GetPlayersRequest(Sport::Nfl)),
        );
    }

    #[Test]
    public function it_sends_every_supported_filter(): void
    {
        $request = new GetPlayersRequest(Sport::Nfl, NflPosition::TeamDefense, activeOnly: true);

        self::assertSame('position=DEF&active=true', $this->queryFor($request));
    }

    /**
     * `active` is a flag rather than a boolean parameter because the API only
     * honours the literal `true` -- `active=false`, `active=0` and
     * `active=nonsense` all return the unfiltered set, so there is no way to
     * ask for only the retired players. Sending `false` would look like a
     * filter and do nothing.
     */
    #[Test]
    public function the_active_flag_is_expressed_by_presence(): void
    {
        $request = new GetPlayersRequest(Sport::Nfl, NflPosition::TeamDefense, activeOnly: false);

        self::assertSame('position=DEF', $this->queryFor($request));
        self::assertStringNotContainsString('active', $this->queryFor($request));
    }

    /**
     * Unset filters are dropped outright, not sent as empty parameters. An
     * `?position=` is not the same request as omitting `position`.
     */
    #[Test]
    public function it_registers_no_empty_parameters(): void
    {
        self::assertSame([], $this->queryParametersFor(new GetPlayersRequest(Sport::Nfl)));
        self::assertSame('', $this->queryFor(new GetPlayersRequest(Sport::Nfl)));

        self::assertSame(
            ['position' => 'DEF'],
            $this->queryParametersFor(new GetPlayersRequest(Sport::Nfl, NflPosition::TeamDefense)),
        );
    }

    /**
     * The body is an object keyed by player ID rather than a list, which is
     * what makes this endpoint the odd one out.
     */
    #[Test]
    public function a_recorded_response_hydrates_a_map_keyed_by_player_id(): void
    {
        $players = $this->defenses();

        self::assertCount(32, $players);
        self::assertArrayHasKey('DET', $players);
    }

    /**
     * The case that breaks anything assuming a player ID is a number. A team
     * defense's ID is its team abbreviation, and it survives as a string.
     */
    #[Test]
    public function a_team_defense_keeps_its_non_numeric_id(): void
    {
        $lions = $this->defenses()['DET'];

        self::assertSame('DET', $lions->playerId);
        self::assertSame('DET', $lions->team);
        self::assertSame('Detroit', $lions->firstName);
        self::assertSame('Lions', $lions->lastName);
        // No `full_name` at all, so the name has to come from the two halves.
        self::assertNull($lions->fullName);
        self::assertSame('Detroit Lions', $lions->name());
        self::assertTrue($lions->active);
        self::assertTrue($lions->isTeamDefense());
        self::assertSame(NflPosition::TeamDefense, $lions->slot());
        self::assertSame(['DEF'], $lions->fantasyPositions);
    }

    /**
     * Ten fields out of fifty. A defense has no age, no college, no height and
     * no external IDs at all, which is the whole reason the DTO is nullable
     * nearly everywhere.
     */
    #[Test]
    public function a_record_missing_most_of_its_fields_still_hydrates(): void
    {
        $lions = $this->defenses()['DET'];

        self::assertNull($lions->age);
        self::assertNull($lions->college);
        self::assertNull($lions->height);
        self::assertNull($lions->weight);
        self::assertNull($lions->number);
        self::assertNull($lions->birthDate);
        self::assertNull($lions->yearsExperience);
        self::assertNull($lions->status);
        self::assertNull($lions->rosterStatus());
        self::assertNull($lions->injuryStatus);
        self::assertNull($lions->injury());
        self::assertNull($lions->depthChartPosition);
        self::assertNull($lions->depthChartOrder);
        self::assertNull($lions->searchRank);
        self::assertNull($lions->externalIds->espn);
        self::assertNull($lions->externalIds->sportradar);
        self::assertNull($lions->externalIds->gsis);
    }

    /**
     * A human record, recorded through the narrowest filter that returns one:
     * `LEO` matches two players in 2.4KB.
     */
    #[Test]
    public function a_human_record_hydrates_every_mapped_field(): void
    {
        $player = $this->edgeRushers()[1513];

        self::assertSame('1513', $player->playerId);
        self::assertSame('Ezekiel', $player->firstName);
        self::assertSame('Ansah', $player->lastName);
        self::assertSame('Ezekiel Ansah', $player->fullName);
        self::assertSame($player->fullName, $player->name());
        self::assertSame('DE', $player->position);
        self::assertNull($player->team);
        self::assertSame(94, $player->number);
        self::assertTrue($player->active);
        self::assertSame('Inactive', $player->status);
        self::assertSame(32, $player->age);
        self::assertSame('1989-05-29', $player->birthDate);
        self::assertSame(9, $player->yearsExperience);
        self::assertSame('Brigham Young', $player->college);
        self::assertSame('77', $player->height);
        self::assertSame(275, $player->weight);
        self::assertSame(9999999, $player->searchRank);
        self::assertSame(RosterStatus::Inactive, $player->rosterStatus());
        self::assertFalse($player->isTeamDefense());
    }

    /**
     * The reason to pull the map at all: a roster gives you Sleeper's IDs and
     * nothing else. Note how unevenly populated they are even for one player.
     */
    #[Test]
    public function a_human_record_carries_its_ids_at_other_services(): void
    {
        $ids = $this->edgeRushers()[1513]->externalIds;

        self::assertSame(15785, $ids->espn);
        self::assertSame(26628, $ids->yahoo);
        self::assertSame('436e0e45-f07a-4674-b21f-4fd8e1f24583', $ids->sportradar);
        self::assertSame(8657, $ids->rotowire);
        self::assertSame(8385, $ids->rotoworld);
        self::assertSame(15122, $ids->fantasyData);
        self::assertSame('00-0030059', $ids->gsis);
        self::assertSame(558874, $ids->stats);
    }

    /**
     * The filter matches fantasy positions rather than the listed one. Asking
     * for `LEO` returns a player whose `position` is `DE`, which is not what
     * the docs' wording suggests.
     */
    #[Test]
    public function the_position_filter_matches_fantasy_eligibility_not_the_listed_position(): void
    {
        $player = $this->edgeRushers()[1513];

        self::assertSame('DE', $player->position);
        self::assertContains('LEO', $player->fantasyPositions);
        self::assertSame(
            [NflPosition::DefensiveLine, NflPosition::Linebacker, NflPosition::Leo],
            $player->eligibleSlots(),
        );
    }

    /**
     * An eligibility slot this client does not know costs the caller that slot
     * and not the player.
     */
    #[Test]
    public function an_unrecognised_eligibility_slot_is_dropped_rather_than_thrown(): void
    {
        $player = Player::fromPayload(Payload::of([
            'player_id' => '1',
            'first_name' => 'A',
            'last_name' => 'B',
            'fantasy_positions' => ['QB', 'WILDCAT', 'RB'],
        ]));

        self::assertSame(
            [NflPosition::Quarterback, NflPosition::RunningBack],
            $player->eligibleSlots(),
        );
        self::assertNull($player->slot());
    }

    /**
     * Height is left raw because Sleeper cannot make its mind up: inches for
     * some players, feet and inches for others, and placeholders for the ones
     * it does not know. An empty string is folded into "absent"; a literal zero
     * is left alone, because inventing a null there would be guessing.
     */
    #[Test]
    public function height_is_left_exactly_as_sent(): void
    {
        self::assertSame('6\'5"', $this->heightOf('6\'5"'));
        self::assertSame('77', $this->heightOf('77'));
        self::assertSame('0', $this->heightOf('0'));
        self::assertNull($this->heightOf(''));
    }

    #[Test]
    public function an_injured_player_carries_a_designation(): void
    {
        $player = Player::fromPayload(Payload::of([
            'player_id' => '1',
            'first_name' => 'A',
            'last_name' => 'B',
            'injury_status' => 'Questionable',
            'injury_body_part' => 'Ankle',
            'injury_notes' => 'Limited in practice.',
        ]));

        self::assertSame(InjuryStatus::Questionable, $player->injury());
        self::assertSame('Ankle', $player->injuryBodyPart);
        self::assertSame('Limited in practice.', $player->injuryNotes);
    }

    /**
     * The endpoint method makes the position mandatory, so the 5MB call cannot
     * happen by leaving an argument off.
     */
    #[Test]
    public function the_endpoint_method_requires_a_position_and_filters_to_active_by_default(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([MockResponse::make(['DET' => ['player_id' => 'DET', 'first_name' => 'Detroit', 'last_name' => 'Lions']])]);
        $connector->withMockClient($mockClient);

        $players = $connector->players(Sport::Nfl, NflPosition::TeamDefense);

        self::assertCount(1, $players);
        self::assertSame('DET', $players['DET']->playerId);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame(['position' => 'DEF', 'active' => 'true'], $pendingRequest->query()->all());
    }

    /**
     * Asking for the whole map has to be said out loud. `everyPlayer()` sends
     * no filters at all, and it is the only way to get there.
     */
    #[Test]
    public function fetching_every_player_is_a_separate_and_deliberate_call(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([MockResponse::make([])]);
        $connector->withMockClient($mockClient);

        self::assertSame([], $connector->everyPlayer(Sport::Nfl));

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame([], $pendingRequest->query()->all());
        self::assertSame('https://api.sleeper.app/v1/players/nfl', (string) $pendingRequest->getUri());
    }

    #[Test]
    public function fetching_every_active_player_still_sends_the_flag(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([MockResponse::make([])]);
        $connector->withMockClient($mockClient);

        $connector->everyPlayer(Sport::Nfl, activeOnly: true);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame(['active' => 'true'], $pendingRequest->query()->all());
    }

    /**
     * A position nobody plays answers `200 {}` rather than an error, so a typo
     * looks like an empty roster rather than a mistake.
     */
    #[Test]
    public function a_filter_matching_nobody_is_an_empty_map(): void
    {
        $connector = new SleeperConnector;
        $connector->withMockClient(new MockClient([MockResponse::make([])]));

        self::assertSame([], $connector->players(Sport::Nfl, NflPosition::Athlete));
    }

    private function heightOf(string $height): ?string
    {
        return Player::fromPayload(Payload::of([
            'player_id' => '1',
            'first_name' => 'A',
            'last_name' => 'B',
            'height' => $height,
        ]))->height;
    }

    /**
     * @return array<array-key, Player>
     */
    private function defenses(): array
    {
        return $this->playerMap(
            new GetPlayersRequest(Sport::Nfl, NflPosition::TeamDefense, activeOnly: true),
            'players/nfl-defenses',
        );
    }

    /**
     * @return array<array-key, Player>
     */
    private function edgeRushers(): array
    {
        return $this->playerMap(
            new GetPlayersRequest(Sport::Nfl, NflPosition::Leo),
            'players/nfl-leo',
        );
    }

    /**
     * @return array<array-key, Player>
     */
    private function playerMap(GetPlayersRequest $request, string $fixture): array
    {
        $players = $this->dtoFrom($request, $fixture);

        self::assertIsArray($players);

        $typed = [];

        foreach ($players as $id => $player) {
            self::assertInstanceOf(Player::class, $player);
            $typed[$id] = $player;
        }

        return $typed;
    }
}
