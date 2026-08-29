<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;

/**
 * One Sleeper account.
 *
 * The response has twenty-one keys and eighteen of them are always null.
 * `email`, `phone`, `real_name`, `token`, `cookies`, `notifications` and the
 * rest are stripped before the API answers, because this endpoint is public
 * and unauthenticated -- they exist in the payload as a shape, not as data.
 * Only what is modelled below has ever been seen carrying a value.
 *
 * `is_bot` arrives as `false` for some accounts and `null` for others with no
 * apparent difference between them, so it is read as "true only when Sleeper
 * says true" rather than trusted to be a tri-state.
 *
 * Note this is not the same shape as a user *within* a league, which drops
 * `username` and adds the league-scoped `metadata` and `is_owner`. That one is
 * `LeagueUser`.
 */
final readonly class User implements ApiDataContract
{
    public function __construct(
        public string $userId,
        public string $username,
        public string $displayName,
        public ?Avatar $avatar,
        public bool $isBot,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        $avatar = $payload->nullableString('avatar');

        return new self(
            userId: $payload->string('user_id'),
            username: $payload->string('username'),
            displayName: $payload->string('display_name'),
            avatar: $avatar === null ? null : new Avatar($avatar),
            isBot: $payload->bool('is_bot'),
        );
    }
}
