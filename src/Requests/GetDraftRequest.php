<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Requests;

use Fantasyphp\Sleeper\Data\Api\Draft;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /draft/{draft_id}: one draft's configuration.
 */
final class GetDraftRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(private readonly string $draftId) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/draft/%s', rawurlencode($this->draftId));
    }

    #[Override]
    public function createDtoFromResponse(Response $response): Draft
    {
        return Draft::fromPayload(Payload::fromResponse($response));
    }
}
