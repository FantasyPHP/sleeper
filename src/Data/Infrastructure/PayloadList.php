<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Infrastructure;

use Fantasyphp\Sleeper\Exceptions\UnexpectedPayloadException;
use Saloon\Http\Response;

/**
 * Typed reader over a response whose whole body is a JSON array.
 *
 * Sleeper has no envelopes. Where another API would wrap a collection in an
 * object carrying a count and some metadata, Sleeper returns the bare array:
 * `/rosters`, `/users`, `/matchups/{week}`, `/transactions/{round}`,
 * `/traded_picks`, the brackets and `/picks` all answer this way. That leaves
 * nothing for `Payload::fromResponse()` to read, since there is no object and
 * so no keys, which is the whole reason this exists as its own reader rather
 * than as fake envelope DTOs invented to give the collections a wrapper they
 * do not have.
 *
 * An empty array is a legitimate answer, not a failure -- a draft with no
 * traded picks and a trending type nobody recognises both come back as `[]`.
 */
final readonly class PayloadList
{
    /**
     * @param  list<Payload>  $items
     */
    private function __construct(private array $items) {}

    /**
     * Decodes the raw body rather than calling Response::json(), for the same
     * reason `Payload::fromResponse()` does: the phpdoc there promises an
     * array and json_decode does not keep that promise for a scalar body.
     */
    public static function fromResponse(Response $response): self
    {
        $decoded = json_decode($response->body(), true);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw UnexpectedPayloadException::notAnArray();
        }

        $items = [];

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                throw UnexpectedPayloadException::expected('an array of objects', 'the response body', $item);
            }

            $items[] = Payload::of($item);
        }

        return new self($items);
    }

    /**
     * Hydrate every element through an item DTO's `fromPayload`.
     *
     * @template TItem
     *
     * @param  callable(Payload): TItem  $hydrate
     * @return list<TItem>
     */
    public function map(callable $hydrate): array
    {
        return array_map($hydrate, $this->items);
    }
}
