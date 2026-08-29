<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Concerns;

use Fantasyphp\Sleeper\Data\Api\SportState;
use Fantasyphp\Sleeper\Enums\Sport;
use Fantasyphp\Sleeper\Requests\GetStateRequest;
use Saloon\Http\Connector;

/**
 * The state endpoint, in the API's own vocabulary.
 *
 * Every method here hydrates through the request's `createDtoFromResponse`
 * rather than `Response::dto()`, which is typed `mixed` and would erase the
 * DTO type at the call site.
 *
 * @mixin Connector
 *
 * @phpstan-require-extends Connector
 */
trait SupportsStateEndpoints
{
    /**
     * GET /state/{sport}: which week and season a sport is currently in.
     *
     * The cheapest call Sleeper has, and the one to reach for before any
     * endpoint that takes a week or a season, since guessing either from the
     * calendar gets the preseason and the playoffs wrong.
     */
    public function state(Sport $sport): SportState
    {
        $request = new GetStateRequest($sport);

        return $request->createDtoFromResponse($this->send($request));
    }
}
