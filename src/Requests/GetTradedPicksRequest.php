<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Requests;

use Fantasyphp\Sleeper\Data\Api\TradedPick;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /league/{league_id}/traded_picks: every pick that has changed hands,
 * including picks in seasons that have not happened yet.
 */
final class GetTradedPicksRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(private readonly string $leagueId) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/league/%s/traded_picks', rawurlencode($this->leagueId));
    }

    /**
     * @return list<TradedPick>
     */
    #[Override]
    public function createDtoFromResponse(Response $response): array
    {
        return PayloadList::fromResponse($response)->map(TradedPick::fromPayload(...));
    }
}
