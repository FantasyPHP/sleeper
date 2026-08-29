<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;

/**
 * One member of a league.
 *
 * A different shape from `User`, despite both being "a user": this one drops
 * `username` and adds the league-scoped `metadata` and `is_owner`. If you need
 * the handle you have to look the account up separately by its `userId`.
 *
 * `metadata` is where the team name lives, along with per-user notification
 * preferences and whichever mascot they picked. It is absent entirely for a
 * member who has never changed a setting.
 *
 * A member who uploaded their own picture also gets a `metadata.avatar`, and
 * that one is an absolute `sleepercdn.com/uploads/...` URL rather than an ID.
 * Do not put it through `Avatar`; the hex `avatar` alongside it still resolves
 * and is the one this DTO models.
 *
 * `settings` is in the payload and has been `null` in every response seen, so
 * it is not modelled.
 *
 * A league has as many of these as it has people, not as many as it has teams:
 * a twelve-roster league with two co-owners answers with fourteen members.
 * Pairing members against rosters one-for-one drops the difference.
 */
final readonly class LeagueUser implements ApiDataContract
{
    /**
     * @param  array<array-key, string>  $metadata
     */
    public function __construct(
        public string $userId,
        public string $leagueId,
        public string $displayName,
        public ?Avatar $avatar,
        public bool $isOwner,
        public bool $isBot,
        public array $metadata,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        $avatar = $payload->nullableString('avatar');

        return new self(
            userId: $payload->string('user_id'),
            leagueId: $payload->string('league_id'),
            displayName: $payload->string('display_name'),
            avatar: $avatar === null ? null : new Avatar($avatar),
            // Read as "true only when Sleeper says true", because the two
            // flags are inconsistent about how they say no. A 2018 league sends
            // `null` for every member who is not a commissioner and not a bot;
            // a 2026 one sends a real `false` for `is_bot` on the same kind of
            // member. Both mean the same thing.
            // `is_owner` means commissioner, and a league can have several.
            isOwner: $payload->bool('is_owner'),
            isBot: $payload->bool('is_bot'),
            metadata: $payload->stringMap('metadata'),
        );
    }

    /**
     * The name on the team, or null if this member never set one.
     *
     * Sleeper falls back to the display name in its own UI when this is
     * missing, which is why it is worth asking for separately rather than
     * assuming `displayName` is the team.
     */
    public function teamName(): ?string
    {
        return $this->metadata['team_name'] ?? null;
    }
}
