<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Exceptions;

use RuntimeException;

use function get_debug_type;

/**
 * Raised when a response does not carry the shape the SDK expects, so a
 * mismatch surfaces as a named error rather than a type juggling surprise
 * three layers further on.
 *
 * Sleeper publishes no machine-readable schema, so this is the SDK's only
 * warning that the API has moved underneath it.
 */
final class UnexpectedPayloadException extends RuntimeException
{
    public static function expected(string $type, string $key, mixed $actual): self
    {
        return new self(sprintf(
            'Expected "%s" to be %s, got %s.',
            $key,
            $type,
            get_debug_type($actual),
        ));
    }

    public static function notAnObject(): self
    {
        return new self('Expected the response body to be a JSON object.');
    }

    public static function notAnArray(): self
    {
        return new self('Expected the response body to be a JSON array.');
    }
}
