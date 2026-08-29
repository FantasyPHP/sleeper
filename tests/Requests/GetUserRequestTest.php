<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\Avatar;
use Fantasyphp\Sleeper\Data\Api\User;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Requests\GetUserRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetUserRequest::class)]
#[CoversClass(User::class)]
#[CoversClass(Avatar::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetUserRequestTest extends RequestTestCase
{
    /**
     * The same slot takes either form, so both have to resolve the same way.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function lookups(): iterable
    {
        yield 'by username' => ['sleeperbot', 'https://api.sleeper.app/v1/user/sleeperbot'];
        yield 'by user id' => ['160004000000400000', 'https://api.sleeper.app/v1/user/160004000000400000'];
    }

    /**
     * A username is user-supplied text and Sleeper allows more in one than a
     * path segment does, so it has to be encoded rather than concatenated.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function awkwardUsernames(): iterable
    {
        yield 'a slash' => ['a/b', 'https://api.sleeper.app/v1/user/a%2Fb'];
        yield 'a space' => ['a b', 'https://api.sleeper.app/v1/user/a%20b'];
        yield 'a question mark' => ['a?b', 'https://api.sleeper.app/v1/user/a%3Fb'];
    }

    #[Test]
    #[DataProvider('lookups')]
    public function it_builds_the_user_path_for_either_form(string $usernameOrId, string $expected): void
    {
        self::assertSame($expected, $this->uriFor(new GetUserRequest($usernameOrId)));
    }

    #[Test]
    #[DataProvider('awkwardUsernames')]
    public function it_encodes_the_path_segment(string $username, string $expected): void
    {
        self::assertSame($expected, $this->uriFor(new GetUserRequest($username)));
    }

    #[Test]
    public function it_sends_no_query_string(): void
    {
        self::assertSame('', $this->queryFor(new GetUserRequest('sleeperbot')));
        self::assertSame([], $this->queryParametersFor(new GetUserRequest('sleeperbot')));
    }

    #[Test]
    public function a_recorded_response_hydrates_every_mapped_field(): void
    {
        $user = $this->dtoFrom(new GetUserRequest('sleeperbot'), 'user/sleeperbot');

        self::assertInstanceOf(User::class, $user);
        self::assertSame('160004000000400000', $user->userId);
        self::assertSame('sleeperbot', $user->username);
        self::assertSame('SleeperBot', $user->displayName);
        self::assertTrue($user->isBot);
        self::assertInstanceOf(Avatar::class, $user->avatar);
        self::assertSame('sleeperbot_black.png', $user->avatar->id);
    }

    /**
     * A human account, to prove the bot flag is read rather than assumed. Note
     * this one sends `is_bot: false` where the account above sends `true` and
     * a third sends `null` -- all three mean the same two things.
     */
    #[Test]
    public function a_human_account_hydrates_as_not_a_bot(): void
    {
        $user = $this->dtoFrom(new GetUserRequest('progamer'), 'user/progamer');

        self::assertInstanceOf(User::class, $user);
        self::assertSame('189140835533586432', $user->userId);
        self::assertSame('progamer', $user->username);
        self::assertFalse($user->isBot);
    }

    /**
     * The behaviour the docs do not describe: a username nobody has is a `200`
     * carrying literal `null`, not a 404. Raising for it would make "no such
     * user" indistinguishable from a genuine failure.
     */
    #[Test]
    public function an_unknown_username_is_an_absence_rather_than_a_failure(): void
    {
        $user = $this->dtoFrom(new GetUserRequest('thisuserdoesnotexist999zzz'), 'user/unknown');

        self::assertNull($user);
    }

    #[Test]
    public function an_account_without_an_avatar_has_no_avatar_urls(): void
    {
        $user = User::fromPayload(Payload::of([
            'user_id' => '1',
            'username' => 'nobody',
            'display_name' => 'Nobody',
            'avatar' => null,
        ]));

        self::assertNull($user->avatar);
    }

    /**
     * Avatars are on a different host from everything else this client talks
     * to, so getting the base URL wrong would produce a 404 nowhere near where
     * the mistake was made.
     */
    #[Test]
    public function an_avatar_builds_both_cdn_urls(): void
    {
        $avatar = new Avatar('39efe65a68f654b055349b745dd6f524');

        self::assertSame(
            'https://sleepercdn.com/avatars/39efe65a68f654b055349b745dd6f524',
            $avatar->url(),
        );
        self::assertSame(
            'https://sleepercdn.com/avatars/thumbs/39efe65a68f654b055349b745dd6f524',
            $avatar->thumbnailUrl(),
        );
    }

    #[Test]
    public function the_endpoint_method_returns_a_typed_user(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([
            MockResponse::make([
                'user_id' => '457511950237696',
                'username' => '2ksports',
                'display_name' => '2KSports',
                'avatar' => '39efe65a68f654b055349b745dd6f524',
                'is_bot' => null,
            ]),
        ]);
        $connector->withMockClient($mockClient);

        $user = $connector->user('2ksports');

        self::assertInstanceOf(User::class, $user);
        self::assertSame('2KSports', $user->displayName);
        self::assertFalse($user->isBot);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame([], $pendingRequest->query()->all());
        self::assertSame('https://api.sleeper.app/v1/user/2ksports', (string) $pendingRequest->getUri());
    }

    #[Test]
    public function the_endpoint_method_returns_null_for_an_unknown_account(): void
    {
        $connector = new SleeperConnector;
        $connector->withMockClient(new MockClient([MockResponse::make('null')]));

        self::assertNull($connector->user('thisuserdoesnotexist999zzz'));
    }
}
