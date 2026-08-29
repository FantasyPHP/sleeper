<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Requests;

use Fantasyphp\Sleeper\Data\Api\League;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /league/{league_id}: one league's configuration.
 *
 * Unlike the user endpoint, a league ID nobody has is a real 404 rather than a
 * 200 carrying null, so a miss surfaces as `NotFoundException`.
 */
final class GetLeagueRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(private readonly string $leagueId) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/league/%s', rawurlencode($this->leagueId));
    }

    #[Override]
    public function createDtoFromResponse(Response $response): League
    {
        return League::fromPayload(Payload::fromResponse($response));
    }
}
