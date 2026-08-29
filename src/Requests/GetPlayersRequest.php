<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Requests;

use Fantasyphp\Sleeper\Data\Api\Player;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Enums\NflPosition;
use Fantasyphp\Sleeper\Enums\Sport;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /players/{sport}: the player map, keyed by player ID.
 *
 * Not a list. The body is one object whose keys are player IDs, which makes it
 * the odd endpoint out among Sleeper's bare arrays.
 *
 * Unfiltered this is roughly 5MB across twelve thousand players and the docs
 * ask that it be called at most once a day. `position` narrows it a great deal:
 * `DEF` returns 32 records in under 6KB.
 *
 * Two things about the filters are not in the docs and were verified against
 * the live API:
 *
 * - `position` matches a player's *fantasy* positions, not his real one.
 *   `?position=LEO` returns players whose `position` is `DE`, because `LEO` is
 *   a slot they are eligible for.
 * - `active` only responds to the literal string `true`. `active=false`,
 *   `active=0` and `active=nonsense` are all ignored and return the unfiltered
 *   set, so there is no way to ask for only the retired players. Presence is
 *   the signal, which is why this takes a flag rather than a nullable boolean.
 *
 * A position nobody has answers `200 {}` rather than an error, so a typo reads
 * as "nobody plays there".
 */
final class GetPlayersRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        private readonly Sport $sport,
        private readonly ?NflPosition $position = null,
        private readonly bool $activeOnly = false,
    ) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/players/%s', $this->sport->value);
    }

    /**
     * The body's own keys are the player IDs, so the map is read through
     * `keys()` rather than out of a named field.
     *
     * Keys stay `array-key` because PHP casts a numeric string key like
     * `"2315"` to an int on the way into the array, while a team defense's
     * `"DET"` stays a string. `Player::playerId` is the authoritative form and
     * is always a string.
     *
     * @return array<array-key, Player>
     */
    #[Override]
    public function createDtoFromResponse(Response $response): array
    {
        $payload = Payload::fromResponse($response);
        $players = [];

        foreach ($payload->keys() as $playerId) {
            $players[$playerId] = Player::fromPayload($payload->object($playerId));
        }

        return $players;
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function defaultQuery(): array
    {
        $query = [
            'position' => $this->position?->value,
            // The API only honours the literal `true`, so the flag is expressed
            // by sending the parameter or not sending it.
            'active' => $this->activeOnly ? 'true' : null,
        ];

        // Send only what was asked for. An empty `?position=` is not the same
        // request as omitting `position`.
        return array_filter($query, static fn (?string $value): bool => $value !== null);
    }
}
