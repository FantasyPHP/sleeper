<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Exceptions;

use Saloon\Http\Response;
use Throwable;

/**
 * Maps a failed response onto the exception type that fits it.
 *
 * Lives here rather than on the connector so the connector stays pure Saloon
 * plumbing (timeouts, retry), with the mapping kept next to the types it
 * produces.
 *
 * There is no body reading, because there is no body worth reading. Sleeper
 * answers a 404 with a literal `null` and the mis-documented `loses_bracket`
 * route with an HTML redirect page; neither carries an explanation. Leaving the
 * message null lets `RequestException` compose its own from the status and
 * body rather than us inventing a worse one.
 */
final class RequestFailure
{
    /**
     * The docs name 400, 404, 429, 500 and 503. There is no 401 or 403 to map,
     * since the read API takes no credentials and so has nothing to reject.
     *
     * Only 404 shows up in practice. Nothing found so far provokes a 400:
     * an unknown sport, an unrecognised trending type and a position that
     * matches no players all answer 200 with an empty body of the right shape.
     */
    public static function toException(Response $response, ?Throwable $previous = null): Throwable
    {
        return match ($response->status()) {
            404 => new NotFoundException($response, previous: $previous),
            429 => new RateLimitException($response, previous: $previous),
            default => new ApiRequestException($response, previous: $previous),
        };
    }
}
