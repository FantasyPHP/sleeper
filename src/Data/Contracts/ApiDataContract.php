<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Contracts;

use Fantasyphp\Sleeper\Data\Infrastructure\Payload;

/**
 * A DTO hydrated from one decoded JSON object.
 *
 * Everything under Data that is built from part of a response reads through
 * `Payload` rather than touching `mixed` itself, so this pins that convention
 * for the DTOs still to come as the remaining endpoints land.
 *
 * The envelope DTOs -- the ones a request hydrates directly from a whole
 * `Response` -- take a `Response` instead, so they're not part of this
 * contract. Sleeper has few of them: most endpoints answer with a bare array
 * whose elements are read straight into item DTOs through `PayloadList`.
 */
interface ApiDataContract
{
    public static function fromPayload(Payload $payload): self;
}
