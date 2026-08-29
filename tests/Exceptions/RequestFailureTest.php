<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Exceptions;

use Fantasyphp\Sleeper\Exceptions\ApiRequestException;
use Fantasyphp\Sleeper\Exceptions\NotFoundException;
use Fantasyphp\Sleeper\Exceptions\RateLimitException;
use Fantasyphp\Sleeper\Exceptions\RequestFailure;
use Fantasyphp\Sleeper\Exceptions\SleeperRequestException;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\Doubles\StubRequest;
use Fantasyphp\Sleeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Throwable;

#[CoversClass(RequestFailure::class)]
#[CoversClass(ApiRequestException::class)]
#[CoversClass(NotFoundException::class)]
#[CoversClass(RateLimitException::class)]
#[CoversClass(SleeperConnector::class)]
final class RequestFailureTest extends TestCase
{
    /**
     * The statuses Sleeper documents that have no mapping of their own, plus
     * one it does not, since anything a gateway invents still has to arrive as
     * a Sleeper type.
     *
     * @return iterable<string, array{int}>
     */
    public static function unmappedStatuses(): iterable
    {
        yield 'bad request' => [400];
        yield 'server fault' => [500];
        yield 'maintenance' => [503];
        yield 'bad gateway' => [502];
    }

    /**
     * Sleeper answers a miss with a JSON `null` on the routes that return JSON
     * at all. The `loses_bracket` route the docs print does not exist and
     * answers with an HTML redirect page instead, so the mapper has to cope
     * with a body it cannot decode.
     *
     * @return iterable<string, array{string}>
     */
    public static function missBodies(): iterable
    {
        yield 'a json null' => ['null'];
        yield 'an html redirect page' => ['<!DOCTYPE html><html lang="en"><head></head><body></body></html>'];
        yield 'nothing at all' => [''];
    }

    #[Test]
    #[DataProvider('missBodies')]
    public function an_unknown_id_is_a_not_found_failure(string $body): void
    {
        $exception = $this->failureFrom(MockResponse::make($body, 404));

        self::assertInstanceOf(NotFoundException::class, $exception);
        self::assertSame(404, $exception->getStatus());
    }

    #[Test]
    public function too_many_calls_is_a_rate_limit_failure(): void
    {
        // Three, because the connector retries a 429 twice before giving up.
        $exception = $this->failureFrom(
            MockResponse::make('null', 429),
            MockResponse::make('null', 429),
            MockResponse::make('null', 429),
        );

        self::assertInstanceOf(RateLimitException::class, $exception);
    }

    #[Test]
    #[DataProvider('unmappedStatuses')]
    public function an_unmapped_failure_is_still_a_sleeper_type(int $status): void
    {
        // A 5xx is retried, so queue enough responses for every attempt.
        $exception = $this->failureFrom(
            MockResponse::make('null', $status),
            MockResponse::make('null', $status),
            MockResponse::make('null', $status),
        );

        self::assertInstanceOf(SleeperRequestException::class, $exception);
        self::assertInstanceOf(ApiRequestException::class, $exception);
        self::assertSame($status, $exception->getStatus());
    }

    /**
     * Sleeper sends no explanation with a failure, so the message is whatever
     * Saloon composes from the status and body. Inventing a better-sounding one
     * out of nothing would only be a worse one.
     */
    #[Test]
    public function a_failure_keeps_saloons_description(): void
    {
        $exception = $this->failureFrom(MockResponse::make('null', 404));

        self::assertStringContainsString('Not Found (404)', $exception->getMessage());
    }

    /**
     * The mapped types stay inside Saloon's `RequestException` hierarchy on
     * purpose. `handleRetry` is typed `FatalRequestException|RequestException`,
     * so an exception outside that union would be a TypeError before the
     * connector could decide whether to retry, silently disabling retries for
     * the transient failures they exist to absorb.
     */
    #[Test]
    public function the_mapped_types_remain_catchable_as_saloon_request_exceptions(): void
    {
        $mockClient = new MockClient([MockResponse::make('null', 404)]);

        // Caught as Throwable rather than through the helper, so the narrowing
        // below is the assertion doing the work rather than a return type.
        try {
            new SleeperConnector()->send(new StubRequest, $mockClient);
            self::fail('The request was expected to fail.');
        } catch (Throwable $throwable) {
            self::assertInstanceOf(RequestException::class, $throwable);
            self::assertInstanceOf(SleeperRequestException::class, $throwable);
            self::assertSame(404, $throwable->getResponse()->status());
        }
    }

    #[Test]
    public function a_rate_limit_is_still_retried_before_it_is_raised(): void
    {
        $mockClient = new MockClient([
            MockResponse::make('null', 429),
            MockResponse::make('null', 429),
            MockResponse::make('null', 429),
        ]);

        try {
            $this->impatientConnector()->send(new StubRequest, $mockClient);
            self::fail('An exhausted allowance should surface as a RateLimitException.');
        } catch (RateLimitException) {
            // Expected. The assertion that matters is the attempt count below.
        }

        self::assertCount(3, $mockClient->getRecordedResponses());
    }

    #[Test]
    public function a_rate_limit_that_clears_on_a_retry_never_surfaces(): void
    {
        $mockClient = new MockClient([
            MockResponse::make('null', 429),
            MockResponse::make(['week' => 3]),
        ]);

        $response = $this->impatientConnector()->send(new StubRequest, $mockClient);

        self::assertTrue($response->successful());
        self::assertCount(2, $mockClient->getRecordedResponses());
    }

    #[Test]
    public function a_miss_is_not_retried(): void
    {
        $mockClient = new MockClient([MockResponse::make('null', 404)]);

        try {
            new SleeperConnector()->send(new StubRequest, $mockClient);
            self::fail('An unknown ID should surface as a NotFoundException.');
        } catch (NotFoundException) {
            // Expected.
        }

        // A second attempt would have drained a queue holding only one response.
        self::assertCount(1, $mockClient->getRecordedResponses());
    }

    /**
     * Send a stub request against queued failures and return what came out.
     */
    private function failureFrom(MockResponse ...$responses): RequestException
    {
        $mockClient = new MockClient(array_values($responses));

        try {
            $this->impatientConnector()->send(new StubRequest, $mockClient);
        } catch (RequestException $requestException) {
            return $requestException;
        }

        self::fail('The request was expected to fail.');
    }

    /**
     * A connector that retries as configured but without waiting between goes.
     *
     * The real 500ms interval and exponential backoff are asserted in
     * `SleeperConnectorTest`; sleeping through them here would only buy the
     * same coverage at several seconds a test, and it made Infection fall back
     * to killing mutants by timeout rather than by assertion.
     */
    private function impatientConnector(): SleeperConnector
    {
        $connector = new SleeperConnector;
        $connector->retryInterval = 0;
        $connector->useExponentialBackoff = false;

        return $connector;
    }
}
