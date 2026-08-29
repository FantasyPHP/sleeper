<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Requests;

use Fantasyphp\Sleeper\Data\Api\League;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Enums\Sport;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /user/{user_id}/leagues/{sport}/{season}: every league an account is in
 * for one season.
 *
 * Takes the numeric user ID and not the username, unlike `/user/{...}`, which
 * accepts either. A username here answers 404.
 */
final class GetLeaguesForUserRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $userId,
        private readonly Sport $sport,
        private readonly string $season,
    ) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf(
            '/user/%s/leagues/%s/%s',
            rawurlencode($this->userId),
            $this->sport->value,
            rawurlencode($this->season),
        );
    }

    /**
     * @return list<League>
     */
    #[Override]
    public function createDtoFromResponse(Response $response): array
    {
        return PayloadList::fromResponse($response)->map(League::fromPayload(...));
    }
}
