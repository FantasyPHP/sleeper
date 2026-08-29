<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Exceptions;

/**
 * A failed Sleeper response, as one type you can catch.
 *
 * Consumers go through the connector's endpoint methods and never touch
 * Saloon, so a raw `Saloon\Exceptions\Request\Statuses\*` coming out of
 * `$connector->league(...)` would leak the abstraction. Every HTTP failure
 * implements this instead: catch it for all of them, or one of the concrete
 * classes for a specific cause.
 *
 * It's an interface and not a base class because Pint enforces `final_class`
 * and `protected_to_private` here, which would finalise a shared parent out
 * from under its own subclasses.
 *
 * Each implementation also extends Saloon's `RequestException`, which matters:
 * `handleRetry()` is typed `FatalRequestException|RequestException` and gets
 * whatever `getRequestException()` returns. Anything outside that union throws
 * a TypeError before the connector can decide whether to retry.
 */
interface SleeperRequestException {}
