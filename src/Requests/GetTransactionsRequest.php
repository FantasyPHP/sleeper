<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Requests;

use Fantasyphp\Sleeper\Data\Api\Transaction;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /league/{league_id}/transactions/{round}: every move made in one week.
 *
 * The path parameter is documented as `round` and described as "the week you
 * want to pull from". It is the week. There is no way to ask for a whole
 * season at once, so walking a league's history means one call per week.
 */
final class GetTransactionsRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $leagueId,
        private readonly int $round,
    ) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/league/%s/transactions/%d', rawurlencode($this->leagueId), $this->round);
    }

    /**
     * @return list<Transaction>
     */
    #[Override]
    public function createDtoFromResponse(Response $response): array
    {
        return PayloadList::fromResponse($response)->map(Transaction::fromPayload(...));
    }
}
