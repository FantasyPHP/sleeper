<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Requests;

use Fantasyphp\Sleeper\Data\Api\Roster;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /league/{league_id}/rosters: every team's squad and season record.
 */
final class GetRostersRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(private readonly string $leagueId) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/league/%s/rosters', rawurlencode($this->leagueId));
    }

    /**
     * @return list<Roster>
     */
    #[Override]
    public function createDtoFromResponse(Response $response): array
    {
        return PayloadList::fromResponse($response)->map(Roster::fromPayload(...));
    }
}
