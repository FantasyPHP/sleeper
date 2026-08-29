<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Exceptions;

use Saloon\Exceptions\Request\RequestException;

/**
 * A failure with no more specific mapping: a 400, a 500, a 503, anything
 * unforeseen.
 *
 * Here so nothing reaches a consumer as a raw Saloon type. Any status the SDK
 * doesn't name explicitly still arrives implementing `SleeperRequestException`.
 */
final class ApiRequestException extends RequestException implements SleeperRequestException {}
