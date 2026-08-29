<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Requests;

use Fantasyphp\Sleeper\Data\Api\Matchup;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /league/{league_id}/matchups/{week}: one week, one row per team.
 *
 * The week is the league's own week number, which is not the calendar week and
 * not necessarily the week `/state/nfl` reports either -- a league can start
 * later than the NFL season does.
 */
final class GetMatchupsRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $leagueId,
        private readonly int $week,
    ) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/league/%s/matchups/%d', rawurlencode($this->leagueId), $this->week);
    }

    /**
     * @return list<Matchup>
     */
    #[Override]
    public function createDtoFromResponse(Response $response): array
    {
        return PayloadList::fromResponse($response)->map(Matchup::fromPayload(...));
    }
}
