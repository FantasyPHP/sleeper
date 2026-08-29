<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Data;

use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Exceptions\UnexpectedPayloadException;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\Doubles\StubRequest;
use Fantasyphp\Sleeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;

#[CoversClass(PayloadList::class)]
#[CoversClass(Payload::class)]
#[CoversClass(UnexpectedPayloadException::class)]
final class PayloadListTest extends TestCase
{
    /**
     * Everything Sleeper could answer with that is not a JSON array.
     *
     * The `null` case is the interesting one: it is what a 404 body looks like,
     * and it must not quietly read as an empty collection.
     *
     * @return iterable<string, array{string}>
     */
    public static function nonArrayBodies(): iterable
    {
        yield 'an object' => ['{"roster_id":1}'];
        yield 'a null' => ['null'];
        yield 'a string' => ['"nope"'];
        yield 'a number' => ['7'];
        yield 'an object keyed by id' => ['{"0":{"roster_id":1},"2":{"roster_id":2}}'];
    }

    #[Test]
    public function it_reads_an_array_response_body(): void
    {
        $list = PayloadList::fromResponse($this->responseWithBody('[{"roster_id":1},{"roster_id":2}]'));

        self::assertSame([1, 2], $list->map(static fn (Payload $item): int => $item->int('roster_id')));
    }

    /**
     * A draft with no traded picks answers `[]`, and so does a trending type
     * nobody recognises. Neither is a failure.
     */
    #[Test]
    public function an_empty_array_is_an_empty_list(): void
    {
        $list = PayloadList::fromResponse($this->responseWithBody('[]'));

        self::assertSame([], $list->map(static fn (Payload $item): int => $item->int('roster_id')));
    }

    #[Test]
    #[DataProvider('nonArrayBodies')]
    public function a_body_that_is_not_an_array_is_an_error(string $body): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected the response body to be a JSON array.');

        PayloadList::fromResponse($this->responseWithBody($body));
    }

    #[Test]
    public function a_scalar_element_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "the response body" to be an array of objects, got string.');

        PayloadList::fromResponse($this->responseWithBody('[{"roster_id":1},"nope"]'));
    }

    private function responseWithBody(string $body): Response
    {
        return new SleeperConnector()->send(
            new StubRequest,
            new MockClient([MockResponse::make($body)]),
        );
    }
}
