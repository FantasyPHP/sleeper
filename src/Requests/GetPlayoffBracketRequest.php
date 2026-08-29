<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Requests;

use Fantasyphp\Sleeper\Data\Api\BracketMatchup;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Enums\BracketType;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /league/{league_id}/winners_bracket and .../losers_bracket: the playoff
 * bracket, one row per match.
 *
 * Both ends of the bracket are the same shape and differ only in the segment,
 * so they are one request class. The segment comes from `BracketType`, which
 * is also where the note lives about the docs printing the losers route wrong.
 */
final class GetPlayoffBracketRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $leagueId,
        private readonly BracketType $bracket,
    ) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/league/%s/%s', rawurlencode($this->leagueId), $this->bracket->value);
    }

    /**
     * @return list<BracketMatchup>
     */
    #[Override]
    public function createDtoFromResponse(Response $response): array
    {
        return PayloadList::fromResponse($response)->map(BracketMatchup::fromPayload(...));
    }
}
