<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Concerns;

use Fantasyphp\Sleeper\Data\Api\User;
use Fantasyphp\Sleeper\Requests\GetUserRequest;
use Saloon\Http\Connector;

/**
 * The user endpoint, in the API's own vocabulary.
 *
 * @mixin Connector
 *
 * @phpstan-require-extends Connector
 */
trait SupportsUserEndpoints
{
    /**
     * GET /user/{username} or GET /user/{user_id}: one Sleeper account.
     *
     * The docs present these as two lookups; the API treats them as one slot
     * that accepts either, so this takes either. Null when there is no such
     * account -- Sleeper reports a miss as a `200` with a `null` body rather
     * than a 404.
     *
     * The numeric `user_id` is the durable handle. A username can be changed
     * by the person who holds it and then taken by somebody else, so anything
     * stored between runs should store the ID.
     */
    public function user(string $usernameOrId): ?User
    {
        $request = new GetUserRequest($usernameOrId);

        return $request->createDtoFromResponse($this->send($request));
    }
}
