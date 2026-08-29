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
 * GET /draft/{draft_id}/traded_picks: the picks in this draft that changed
 * hands before it ran.
 *
 * Narrower than the league route of the same name, which also covers picks in
 * seasons whose draft does not exist yet.
 */
final class GetDraftTradedPicksRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(private readonly string $draftId) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/draft/%s/traded_picks', rawurlencode($this->draftId));
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
