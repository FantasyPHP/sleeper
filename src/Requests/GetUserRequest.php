<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Requests;

use Fantasyphp\Sleeper\Data\Api\User;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /user/{username} and GET /user/{user_id}: one Sleeper account.
 *
 * The docs list these as two endpoints. They are one: the path segment accepts
 * either form and the route does not care which it was given, so there is one
 * request class rather than two identical ones.
 */
final class GetUserRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(private readonly string $usernameOrId) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/user/%s', rawurlencode($this->usernameOrId));
    }

    /**
     * Null when Sleeper has no such account.
     *
     * A miss here is not a 404 -- the endpoint answers `200` with a body of
     * literal `null`, for a username nobody has and for a user ID nobody has
     * alike. Raising for that would make "no such user" indistinguishable from
     * a genuine failure, so the absence is returned instead.
     */
    #[Override]
    public function createDtoFromResponse(Response $response): ?User
    {
        $payload = Payload::fromResponseOrNull($response);

        return $payload instanceof Payload ? User::fromPayload($payload) : null;
    }
}
