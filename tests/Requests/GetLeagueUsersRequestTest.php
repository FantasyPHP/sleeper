<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\Avatar;
use Fantasyphp\Sleeper\Data\Api\LeagueUser;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Requests\GetLeagueUsersRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetLeagueUsersRequest::class)]
#[CoversClass(LeagueUser::class)]
#[CoversClass(Avatar::class)]
#[CoversClass(PayloadList::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetLeagueUsersRequestTest extends RequestTestCase
{
    private const string LEAGUE_ID = '289646328504385536';

    /**
     * A league still filling up: twelve rosters between three people. Live, so
     * a `fixtures:refresh` that stops matching means it has filled -- re-point
     * this at another partly-joined league rather than relaxing the assertions.
     */
    private const string PRE_DRAFT_LEAGUE_ID = '1399461312144084992';

    #[Test]
    public function it_builds_the_league_users_path(): void
    {
        self::assertSame(
            'https://api.sleeper.app/v1/league/289646328504385536/users',
            $this->uriFor(new GetLeagueUsersRequest(self::LEAGUE_ID)),
        );
    }

    #[Test]
    public function it_sends_no_query_string(): void
    {
        self::assertSame('', $this->queryFor(new GetLeagueUsersRequest(self::LEAGUE_ID)));
        self::assertSame([], $this->queryParametersFor(new GetLeagueUsersRequest(self::LEAGUE_ID)));
    }

    #[Test]
    public function a_recorded_response_hydrates_every_mapped_field(): void
    {
        $member = $this->firstMember();

        self::assertSame('457511950237696', $member->userId);
        self::assertSame(self::LEAGUE_ID, $member->leagueId);
        self::assertSame('2KSports', $member->displayName);
        self::assertTrue($member->isOwner);
        self::assertFalse($member->isBot);
        self::assertInstanceOf(Avatar::class, $member->avatar);
        self::assertSame('cc12ec49965eb7856f84d71cf85306af', $member->avatar->id);
        self::assertSame('on', $member->metadata['allow_pn']);
    }

    /**
     * The team name is in `metadata` rather than being a field of its own, and
     * it is what a human calls the team -- `displayName` is the account behind
     * it.
     */
    #[Test]
    public function the_team_name_comes_out_of_the_metadata(): void
    {
        self::assertSame('Giant Dolphins', $this->firstMember()->teamName());
    }

    #[Test]
    public function a_member_who_never_named_their_team_has_no_team_name(): void
    {
        $member = LeagueUser::fromPayload(Payload::of([
            'user_id' => '1',
            'league_id' => self::LEAGUE_ID,
            'display_name' => 'somebody',
            'avatar' => null,
            'metadata' => null,
        ]));

        self::assertNull($member->teamName());
        self::assertNull($member->avatar);
        self::assertSame([], $member->metadata);
        self::assertFalse($member->isOwner);
        self::assertFalse($member->isBot);
    }

    /**
     * `is_owner` is only ever `true` or `null` in a response -- there is no
     * `false` -- so a member reading as not a commissioner is a member the API
     * said nothing about. There can be several: this league has four.
     */
    #[Test]
    public function every_commissioner_is_flagged_as_an_owner(): void
    {
        $commissioners = array_filter(
            $this->members(),
            static fn (LeagueUser $member): bool => $member->isOwner,
        );

        self::assertCount(4, $commissioners);
    }

    /**
     * A league has as many members as it has people, not as many as it has
     * teams. This one runs twelve rosters between fourteen members, so pairing
     * members with rosters one-for-one would drop two of them.
     */
    #[Test]
    public function a_league_can_have_more_members_than_rosters(): void
    {
        self::assertCount(14, $this->members());
    }

    /**
     * The other direction from the league above: three members against twelve
     * rosters. Members and teams do not correspond either way round.
     */
    #[Test]
    public function a_league_can_have_fewer_members_than_rosters(): void
    {
        self::assertCount(3, $this->preDraftMembers());
    }

    /**
     * The 2018 league sends `null` for every member who is neither a
     * commissioner nor a bot; this one sends a real `false` for `is_bot` on the
     * same kind of member. Reading only a literal `true` as true is what makes
     * both hydrate the same way.
     */
    #[Test]
    public function the_flags_hydrate_the_same_whether_sent_as_false_or_as_null(): void
    {
        $members = $this->preDraftMembers();

        $commissioners = array_filter(
            $members,
            static fn (LeagueUser $member): bool => $member->isOwner,
        );

        self::assertCount(1, $commissioners);

        foreach ($members as $member) {
            self::assertFalse($member->isBot);
        }
    }

    /**
     * A member who uploaded a picture gets a second avatar in `metadata`, and
     * that one is already an absolute URL on a different path. The hex ID
     * alongside it still resolves, and it is the one that goes through
     * `Avatar` -- putting the metadata value through it would double the host.
     */
    #[Test]
    public function a_custom_uploaded_avatar_sits_alongside_the_hex_one(): void
    {
        $member = $this->preDraftMembers()[0];

        self::assertInstanceOf(Avatar::class, $member->avatar);
        self::assertStringStartsWith('https://sleepercdn.com/avatars/', $member->avatar->url());
        self::assertStringStartsWith('https://sleepercdn.com/uploads/', $member->metadata['avatar']);
    }

    #[Test]
    public function the_endpoint_method_returns_typed_members(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([
            MockResponse::make([[
                'user_id' => '457511950237696',
                'league_id' => self::LEAGUE_ID,
                'display_name' => '2KSports',
                'metadata' => ['team_name' => 'Giant Dolphins'],
            ]]),
        ]);
        $connector->withMockClient($mockClient);

        $members = $connector->leagueUsers(self::LEAGUE_ID);

        self::assertCount(1, $members);
        self::assertSame('Giant Dolphins', $members[0]->teamName());

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame([], $pendingRequest->query()->all());
    }

    /**
     * @return list<LeagueUser>
     */
    private function preDraftMembers(): array
    {
        return $this->hydrate(self::PRE_DRAFT_LEAGUE_ID, 'league/preseason-users');
    }

    /**
     * @return list<LeagueUser>
     */
    private function members(): array
    {
        return $this->hydrate(self::LEAGUE_ID, 'league/users');
    }

    /**
     * @return list<LeagueUser>
     */
    private function hydrate(string $leagueId, string $fixture): array
    {
        $members = $this->dtoFrom(new GetLeagueUsersRequest($leagueId), $fixture);

        self::assertIsArray($members);

        $typed = [];

        foreach ($members as $member) {
            self::assertInstanceOf(LeagueUser::class, $member);
            $typed[] = $member;
        }

        return $typed;
    }

    private function firstMember(): LeagueUser
    {
        return $this->members()[0];
    }
}
