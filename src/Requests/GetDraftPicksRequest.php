<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Requests;

use Fantasyphp\Sleeper\Data\Api\DraftPick;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /draft/{draft_id}/picks: every selection made, in pick order.
 *
 * A draft nobody has started answers `[]` rather than 404, so an empty list
 * here means "not yet", not "no such draft".
 */
final class GetDraftPicksRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(private readonly string $draftId) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/draft/%s/picks', rawurlencode($this->draftId));
    }

    /**
     * @return list<DraftPick>
     */
    #[Override]
    public function createDtoFromResponse(Response $response): array
    {
        return PayloadList::fromResponse($response)->map(DraftPick::fromPayload(...));
    }
}
