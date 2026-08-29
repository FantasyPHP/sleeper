<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Exceptions;

use Saloon\Exceptions\Request\RequestException;

/**
 * Sleeper is refusing calls because too many arrived too quickly.
 *
 * The documented ceiling is roughly 1000 calls per minute, above which the
 * risk is an IP block rather than a 429, so treat this as the polite warning
 * that precedes a much less polite one.
 *
 * Reaching this means every retry was already spent: the connector treats 429
 * as transient and makes three attempts with exponential backoff, so this is
 * the outcome after the last one failed.
 */
final class RateLimitException extends RequestException implements SleeperRequestException {}
