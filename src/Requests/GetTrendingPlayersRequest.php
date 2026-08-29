<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Requests;

use Fantasyphp\Sleeper\Data\Api\TrendingPlayer;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Enums\Sport;
use Fantasyphp\Sleeper\Enums\TrendingType;
use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * GET /players/{sport}/trending/{type}: who is being added or dropped.
 *
 * Sleeper defaults the window to 24 hours and the result to 25 players; both
 * are omitted here unless asked for, so the defaults stay theirs rather than
 * being pinned by this client.
 *
 * Sleeper asks for attribution when you publish this data.
 */
final class GetTrendingPlayersRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        private readonly Sport $sport,
        private readonly TrendingType $type,
        private readonly ?int $lookbackHours = null,
        private readonly ?int $limit = null,
    ) {}

    #[Override]
    public function resolveEndpoint(): string
    {
        return sprintf('/players/%s/trending/%s', $this->sport->value, $this->type->value);
    }

    /**
     * @return list<TrendingPlayer>
     */
    #[Override]
    public function createDtoFromResponse(Response $response): array
    {
        return PayloadList::fromResponse($response)->map(TrendingPlayer::fromPayload(...));
    }

    /**
     * @return array<string, int>
     */
    #[Override]
    protected function defaultQuery(): array
    {
        $query = [
            'lookback_hours' => $this->lookbackHours,
            'limit' => $this->limit,
        ];

        // Send only what was asked for. An empty `?limit=` is not the same
        // request as omitting `limit`.
        return array_filter($query, static fn (?int $value): bool => $value !== null);
    }
}
