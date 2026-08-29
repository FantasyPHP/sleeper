<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Concerns;

use Fantasyphp\Sleeper\Data\Api\Player;
use Fantasyphp\Sleeper\Data\Api\TrendingPlayer;
use Fantasyphp\Sleeper\Enums\NflPosition;
use Fantasyphp\Sleeper\Enums\Sport;
use Fantasyphp\Sleeper\Enums\TrendingType;
use Fantasyphp\Sleeper\Requests\GetPlayersRequest;
use Fantasyphp\Sleeper\Requests\GetTrendingPlayersRequest;
use Saloon\Http\Connector;

/**
 * The player endpoints, in the API's own vocabulary.
 *
 * Every method here hydrates through the request's `createDtoFromResponse`
 * rather than `Response::dto()`, which is typed `mixed` and would erase the
 * DTO type at the call site.
 *
 * @mixin Connector
 *
 * @phpstan-require-extends Connector
 */
trait SupportsPlayerEndpoints
{
    /**
     * GET /players/{sport}?position=…: the players eligible at one position.
     *
     * The position is required rather than optional, and that is deliberate:
     * omitting it fetches all twelve thousand players and about 5MB, which the
     * docs ask you to do at most once a day. If you genuinely want that, say so
     * with `everyPlayer()`.
     *
     * The filter matches *fantasy* positions, not a player's listed one, so
     * `NflPosition::Leo` returns players whose position is `DE`.
     *
     * Keys are the player IDs. PHP casts the numeric ones to int on the way
     * into the array; `Player::playerId` is always the authoritative string.
     *
     * @return array<array-key, Player>
     */
    public function players(Sport $sport, NflPosition $position, bool $activeOnly = true): array
    {
        $request = new GetPlayersRequest($sport, $position, $activeOnly);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /players/{sport}: every player Sleeper knows about.
     *
     * Roughly 5MB and twelve thousand records, most of them retired. The docs
     * ask that this be called at most once a day, so cache the result and drive
     * everything else off the cache -- this is not a lookup, it is a download.
     *
     * `players()` is the one to reach for if you know which position you want.
     *
     * @return array<array-key, Player>
     */
    public function everyPlayer(Sport $sport, bool $activeOnly = false): array
    {
        $request = new GetPlayersRequest($sport, activeOnly: $activeOnly);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /players/{sport}/trending/{type}: who is being added or dropped.
     *
     * Returns IDs and counts and nothing else, so putting names to them means
     * a player-map lookup. Leave `lookbackHours` and `limit` null to take
     * Sleeper's own defaults of 24 hours and 25 players.
     *
     * @return list<TrendingPlayer>
     */
    public function trendingPlayers(
        Sport $sport,
        TrendingType $type,
        ?int $lookbackHours = null,
        ?int $limit = null,
    ): array {
        $request = new GetTrendingPlayersRequest($sport, $type, $lookbackHours, $limit);

        return $request->createDtoFromResponse($this->send($request));
    }
}
