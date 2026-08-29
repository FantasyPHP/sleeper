<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Requests;

use Fantasyphp\Sleeper\Data\Api\Draft;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Enums\Sport;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /user/{user_id}/drafts/{sport}/{season}: every draft an account took
 * part in for one season.
 *
 * Takes the numeric user ID, not the username.
 */
final class GetDraftsForUserRequest extends Request
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
            '/user/%s/drafts/%s/%s',
            rawurlencode($this->userId),
            $this->sport->value,
            rawurlencode($this->season),
        );
    }

    /**
     * @return list<Draft>
     */
    #[Override]
    public function createDtoFromResponse(Response $response): array
    {
        return PayloadList::fromResponse($response)->map(Draft::fromPayload(...));
    }
}
