<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Exceptions;

use Saloon\Exceptions\Request\RequestException;

/**
 * Sleeper has no record under that ID.
 *
 * The usual cause is an ID from another league, another season, or a typo. The
 * body is a literal `null`, so there is nothing to read out of it beyond the
 * status.
 *
 * Not every miss arrives here. Looking a user up by username answers `200` with
 * a `null` body instead of a 404, and a filter that matches nothing answers
 * `200` with an empty collection. Those are absences, not failures, and the
 * endpoint methods model them as such.
 *
 * Never retried: an ID that does not exist will not start existing.
 */
final class NotFoundException extends RequestException implements SleeperRequestException {}
