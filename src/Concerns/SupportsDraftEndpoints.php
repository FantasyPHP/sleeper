<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Concerns;

use Fantasyphp\Sleeper\Data\Api\Draft;
use Fantasyphp\Sleeper\Data\Api\DraftPick;
use Fantasyphp\Sleeper\Data\Api\TradedPick;
use Fantasyphp\Sleeper\Enums\Sport;
use Fantasyphp\Sleeper\Requests\GetDraftPicksRequest;
use Fantasyphp\Sleeper\Requests\GetDraftRequest;
use Fantasyphp\Sleeper\Requests\GetDraftsForUserRequest;
use Fantasyphp\Sleeper\Requests\GetDraftTradedPicksRequest;
use Fantasyphp\Sleeper\Requests\GetLeagueDraftsRequest;
use Saloon\Http\Connector;

/**
 * The draft endpoints, in the API's own vocabulary.
 *
 * Every method here hydrates through the request's `createDtoFromResponse`
 * rather than `Response::dto()`, which is typed `mixed` and would erase the
 * DTO type at the call site.
 *
 * @mixin Connector
 *
 * @phpstan-require-extends Connector
 */
trait SupportsDraftEndpoints
{
    /**
     * GET /user/{user_id}/drafts/{sport}/{season}: every draft an account took
     * part in for one season.
     *
     * @return list<Draft>
     */
    public function draftsForUser(string $userId, Sport $sport, string $season): array
    {
        $request = new GetDraftsForUserRequest($userId, $sport, $season);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /league/{league_id}/drafts: every draft a league has held, most
     * recent first.
     *
     * A `League`'s own `draftId` is only the latest of these; a dynasty league
     * drafts every season.
     *
     * @return list<Draft>
     */
    public function leagueDrafts(string $leagueId): array
    {
        $request = new GetLeagueDraftsRequest($leagueId);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /draft/{draft_id}: one draft's configuration.
     */
    public function draft(string $draftId): Draft
    {
        $request = new GetDraftRequest($draftId);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /draft/{draft_id}/picks: every selection made, in pick order.
     *
     * Each pick carries a snapshot of the player as he was when it was made,
     * so rendering an old draft needs no second call -- and the snapshot is
     * stale by design.
     *
     * @return list<DraftPick>
     */
    public function draftPicks(string $draftId): array
    {
        $request = new GetDraftPicksRequest($draftId);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /draft/{draft_id}/traded_picks: the picks in this draft that changed
     * hands before it ran.
     *
     * @return list<TradedPick>
     */
    public function draftTradedPicks(string $draftId): array
    {
        $request = new GetDraftTradedPicksRequest($draftId);

        return $request->createDtoFromResponse($this->send($request));
    }
}
