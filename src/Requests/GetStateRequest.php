<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Requests;

use Fantasyphp\Sleeper\Data\Api\SportState;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Enums\Sport;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /state/{sport}: which week and season a sport is currently in.
 */
final class GetStateRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(private readonly Sport $sport) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/state/%s', $this->sport->value);
    }

    #[Override]
    public function createDtoFromResponse(Response $response): SportState
    {
        return SportState::fromPayload(Payload::fromResponse($response));
    }
}
