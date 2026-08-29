<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Requests;

use Fantasyphp\Sleeper\Data\Api\Draft;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /league/{league_id}/drafts: every draft a league has held.
 *
 * Most leagues have one. A dynasty league has one per season, and they come
 * back most recent first.
 */
final class GetLeagueDraftsRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(private readonly string $leagueId) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/league/%s/drafts', rawurlencode($this->leagueId));
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
