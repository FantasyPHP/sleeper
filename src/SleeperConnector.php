<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper;

use Fantasyphp\Sleeper\Concerns\SupportsDraftEndpoints;
use Fantasyphp\Sleeper\Concerns\SupportsLeagueEndpoints;
use Fantasyphp\Sleeper\Concerns\SupportsPlayerEndpoints;
use Fantasyphp\Sleeper\Concerns\SupportsStateEndpoints;
use Fantasyphp\Sleeper\Concerns\SupportsUserEndpoints;
use Fantasyphp\Sleeper\Exceptions\RequestFailure;
use Override;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Saloon\Traits\Plugins\HasTimeout;
use Throwable;

/**
 * The Sleeper read API.
 *
 * There is nothing to configure. Sleeper performs no authentication at all --
 * no key, no header, no account -- because the API is read-only and serves
 * only league information that is already public. Construct it and call an
 * endpoint.
 *
 * The constraint that does exist is volume. Sleeper documents a soft ceiling
 * of roughly 1000 calls per minute and warns that exceeding it risks an IP
 * block, which is a good deal harder to recover from than a 429. The retry
 * policy below absorbs the occasional transient failure; it is not a licence
 * to hammer the API.
 */
final class SleeperConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;
    use HasTimeout;
    use SupportsDraftEndpoints;
    use SupportsLeagueEndpoints;
    use SupportsPlayerEndpoints;
    use SupportsStateEndpoints;
    use SupportsUserEndpoints;

    public const string BASE_URL = 'https://api.sleeper.app/v1';

    public function __construct()
    {
        $this->tries = 3;
        $this->retryInterval = 500;
        $this->useExponentialBackoff = true;
    }

    #[Override]
    public function resolveBaseUrl(): string
    {
        return self::BASE_URL;
    }

    public function getConnectTimeout(): float
    {
        return 10;
    }

    public function getRequestTimeout(): float
    {
        return 30;
    }

    #[Override]
    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        if ($exception instanceof FatalRequestException) {
            return true;
        }

        $status = $exception->getResponse()->status();

        return $status === 429 || $status >= 500;
    }

    #[Override]
    public function getRequestException(Response $response, ?Throwable $senderException): Throwable
    {
        return RequestFailure::toException($response, $senderException);
    }
}
