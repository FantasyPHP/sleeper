<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Doubles;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * A do-nothing request, so connector behaviour can be tested without leaning
 * on the real endpoint classes.
 */
final class StubRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    #[Override]
    public function resolveEndpoint(): string
    {
        return '/state/nfl';
    }
}
