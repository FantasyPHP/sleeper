<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests;

use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\Doubles\StubRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(SleeperConnector::class)]
final class SleeperConnectorTest extends TestCase
{
    #[Test]
    public function it_targets_the_documented_base_url(): void
    {
        self::assertSame('https://api.sleeper.app/v1', new SleeperConnector()->resolveBaseUrl());
    }

    #[Test]
    public function it_asks_for_json(): void
    {
        $mockClient = new MockClient([MockResponse::make(['week' => 3])]);

        new SleeperConnector()->send(new StubRequest, $mockClient);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame('application/json', $pendingRequest->headers()->get('Accept'));
    }

    /**
     * Sleeper performs no authentication, so the SDK must not invent any. A
     * stray Authorization header would be a bug, not a harmless extra.
     */
    #[Test]
    public function it_sends_no_credentials(): void
    {
        $mockClient = new MockClient([MockResponse::make(['week' => 3])]);

        $connector = new SleeperConnector;
        $connector->send(new StubRequest, $mockClient);

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNull($connector->getAuthenticator());
        self::assertNotNull($pendingRequest);
        self::assertNull($pendingRequest->headers()->get('Authorization'));
    }

    #[Test]
    public function it_uses_the_documented_timeouts(): void
    {
        $connector = new SleeperConnector;

        self::assertSame(10.0, $connector->getConnectTimeout());
        self::assertSame(30.0, $connector->getRequestTimeout());
    }

    #[Test]
    public function it_allows_three_attempts_with_exponential_backoff(): void
    {
        $connector = new SleeperConnector;

        self::assertSame(3, $connector->tries);
        self::assertSame(500, $connector->retryInterval);
        self::assertTrue($connector->useExponentialBackoff);
    }

    #[Test]
    public function it_retries_rate_limiting(): void
    {
        $mockClient = new MockClient([
            MockResponse::make('null', 429),
            MockResponse::make(['week' => 3]),
        ]);

        $response = $this->impatientConnector()->send(new StubRequest, $mockClient);

        self::assertTrue($response->successful());
        self::assertCount(2, $mockClient->getRecordedResponses());
    }

    /**
     * Both server faults the docs name, since 503 is the one Sleeper says to
     * expect during maintenance and is the likelier of the two to be transient.
     */
    #[Test]
    public function it_retries_a_server_fault(): void
    {
        foreach ([500, 503] as $status) {
            $mockClient = new MockClient([
                MockResponse::make('null', $status),
                MockResponse::make(['week' => 3]),
            ]);

            $response = $this->impatientConnector()->send(new StubRequest, $mockClient);

            self::assertTrue($response->successful());
            self::assertCount(2, $mockClient->getRecordedResponses());
        }
    }

    /**
     * A connection that never opened has no response to inspect, so there is
     * nothing to decide on beyond "try again".
     */
    #[Test]
    public function it_retries_a_connection_that_never_reached_sleeper(): void
    {
        $connector = new SleeperConnector;

        $failure = new FatalRequestException(
            new RuntimeException('could not resolve host'),
            $connector->createPendingRequest(new StubRequest),
        );

        self::assertTrue($connector->handleRetry($failure, new StubRequest));
    }

    /**
     * An ID that does not exist will not start existing, so a 404 is spent on
     * the first attempt rather than three.
     */
    #[Test]
    public function it_does_not_retry_a_miss(): void
    {
        $mockClient = new MockClient([MockResponse::make('null', 404)]);

        try {
            new SleeperConnector()->send(new StubRequest, $mockClient);
            self::fail('A 404 should surface as a RequestException.');
        } catch (RequestException $requestException) {
            self::assertSame(404, $requestException->getResponse()->status());
        }

        // A second attempt would have drained the single queued response.
        self::assertCount(1, $mockClient->getRecordedResponses());
    }

    #[Test]
    public function it_does_not_retry_a_rejected_request(): void
    {
        $mockClient = new MockClient([MockResponse::make('null', 400)]);

        try {
            new SleeperConnector()->send(new StubRequest, $mockClient);
            self::fail('A 400 should surface as a RequestException.');
        } catch (RequestException $requestException) {
            self::assertSame(400, $requestException->getResponse()->status());
        }

        self::assertCount(1, $mockClient->getRecordedResponses());
    }

    /**
     * A connector that retries as configured but without waiting between goes.
     *
     * The real 500ms interval and exponential backoff are asserted above;
     * sleeping through them in every retry test would only buy the same
     * coverage at several seconds a test.
     */
    private function impatientConnector(): SleeperConnector
    {
        $connector = new SleeperConnector;
        $connector->retryInterval = 0;
        $connector->useExponentialBackoff = false;

        return $connector;
    }
}
