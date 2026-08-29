<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Data;

use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
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

#[CoversClass(Payload::class)]
#[CoversClass(UnexpectedPayloadException::class)]
final class PayloadTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, ?int}>
     */
    public static function integerCandidates(): iterable
    {
        yield 'int' => [42, 42];
        yield 'negative int' => [-7, -7];
        yield 'numeric string' => ['2018', 2018];
        yield 'negative numeric string' => ['-7', -7];
        yield 'zero string' => ['0', 0];
        yield 'trailing letters' => ['12abc', null];
        yield 'leading letters' => ['abc12', null];
        yield 'decimal string' => ['1.5', null];
        yield 'padded string' => [' 12', null];
        yield 'empty string' => ['', null];
        yield 'bool' => [true, null];
        yield 'array' => [[1], null];
        yield 'null' => [null, null];
    }

    /**
     * @return iterable<string, array{mixed, ?float}>
     */
    public static function floatCandidates(): iterable
    {
        yield 'decimal string' => ['43.1', 43.1];
        // A starter who scored nothing all week. Zero is a real result and
        // must not read as "no result".
        yield 'zero string' => ['0', 0.0];
        yield 'one string' => ['1', 1.0];
        yield 'float' => [0.5, 0.5];
        yield 'int' => [1, 1.0];
        yield 'letters' => ['some', null];
        yield 'empty string' => ['', null];
        yield 'bool' => [true, null];
        yield 'array' => [[43.1], null];
        yield 'null' => [null, null];
    }

    /**
     * @return iterable<string, array{callable(Payload, string): array<array-key, mixed>}>
     */
    public static function mapReaders(): iterable
    {
        yield 'intMap' => [static fn (Payload $payload, string $key): array => $payload->intMap($key)];
        yield 'floatMap' => [static fn (Payload $payload, string $key): array => $payload->floatMap($key)];
        yield 'stringMap' => [static fn (Payload $payload, string $key): array => $payload->stringMap($key)];
        yield 'boolMap' => [static fn (Payload $payload, string $key): array => $payload->boolMap($key)];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonTrueValues(): iterable
    {
        yield 'string true' => ['true'];
        yield 'one' => [1];
        yield 'string one' => ['1'];
        yield 'false' => [false];
        yield 'null' => [null];
    }

    #[Test]
    public function it_reads_a_string(): void
    {
        self::assertSame('Todd', Payload::of(['first_name' => 'Todd'])->string('first_name'));
    }

    /**
     * The API is inconsistent about quoting numbers -- a draft pick's metadata
     * sends the player ID as a string, the pick itself sends the same ID
     * unquoted -- so a numeric value read as a string is coerced rather than
     * rejected.
     */
    #[Test]
    public function it_coerces_numbers_to_strings(): void
    {
        self::assertSame('2315', Payload::of(['player_id' => 2315])->string('player_id'));
        self::assertSame('1.5', Payload::of(['player_id' => 1.5])->string('player_id'));
    }

    #[Test]
    public function a_missing_string_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "name" to be a string, got null.');

        Payload::of([])->string('name');
    }

    #[Test]
    public function a_non_string_value_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "name" to be a string, got array.');

        Payload::of(['name' => ['nested']])->string('name');
    }

    #[Test]
    public function a_nullable_string_tolerates_absence(): void
    {
        self::assertNull(Payload::of([])->nullableString('name'));
        self::assertNull(Payload::of(['name' => ['nested']])->nullableString('name'));
    }

    #[Test]
    #[DataProvider('integerCandidates')]
    public function it_reads_integers_strictly(mixed $value, ?int $expected): void
    {
        self::assertSame($expected, Payload::of(['rank' => $value])->nullableInt('rank'));
    }

    #[Test]
    public function a_non_integer_value_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "round" to be an integer, got string.');

        Payload::of(['round' => 'first'])->int('round');
    }

    /**
     * Only a literal JSON `true` counts, so a "false"-ish string is not truthy.
     */
    #[Test]
    #[DataProvider('nonTrueValues')]
    public function only_a_real_boolean_true_is_true(mixed $value): void
    {
        self::assertFalse(Payload::of(['active' => $value])->bool('active'));
    }

    #[Test]
    public function it_reads_a_true_boolean(): void
    {
        self::assertTrue(Payload::of(['active' => true])->bool('active'));
    }

    #[Test]
    #[DataProvider('floatCandidates')]
    public function it_reads_floats_from_numeric_strings(mixed $value, ?float $expected): void
    {
        self::assertSame($expected, Payload::of(['points' => $value])->nullableFloat('points'));
    }

    #[Test]
    public function it_reads_every_item_of_an_integer_list(): void
    {
        self::assertSame([4, 6], Payload::of(['consenter_ids' => [4, 6]])->ints('consenter_ids'));
    }

    #[Test]
    public function a_missing_integer_list_is_empty(): void
    {
        self::assertSame([], Payload::of([])->ints('consenter_ids'));
    }

    #[Test]
    public function a_non_integer_item_in_a_list_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "consenter_ids" to be a list of integers, got string.');

        Payload::of(['consenter_ids' => [4, '6']])->ints('consenter_ids');
    }

    #[Test]
    public function it_reads_every_item_of_a_string_list(): void
    {
        self::assertSame(
            ['4881', '788', 'CLE'],
            Payload::of(['starters' => ['4881', '788', 'CLE']])->strings('starters'),
        );
    }

    #[Test]
    public function a_missing_string_list_is_empty(): void
    {
        self::assertSame([], Payload::of([])->strings('starters'));
    }

    #[Test]
    public function a_non_string_item_in_a_list_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "starters" to be a list of strings, got int.');

        Payload::of(['starters' => ['CLE', 788]])->strings('starters');
    }

    #[Test]
    public function it_reads_a_map_of_integers(): void
    {
        self::assertSame(
            ['wins' => 7, 'losses' => 6],
            Payload::of(['settings' => ['wins' => 7, 'losses' => 6]])->intMap('settings'),
        );
    }

    /**
     * A draft order is keyed by user ID and its slots arrive unquoted, but the
     * same shape elsewhere quotes them, so both have to read the same.
     */
    #[Test]
    public function a_map_of_integers_takes_numeric_strings(): void
    {
        self::assertSame(
            [457511950237696 => 8, 521611313037312 => 3],
            Payload::of(['draft_order' => ['457511950237696' => '8', '521611313037312' => 3]])->intMap('draft_order'),
        );
    }

    #[Test]
    public function it_reads_a_map_of_numbers_mixing_integers_and_decimals(): void
    {
        self::assertSame(
            ['788' => 22.0, '421' => 10.04],
            Payload::of(['players_points' => ['788' => 22, '421' => 10.04]])->floatMap('players_points'),
        );
    }

    #[Test]
    public function it_reads_a_map_of_strings(): void
    {
        self::assertSame(
            ['team_name' => 'Giant Dolphins'],
            Payload::of(['metadata' => ['team_name' => 'Giant Dolphins']])->stringMap('metadata'),
        );
    }

    /**
     * Same rule as bool(): only a literal JSON true counts.
     */
    #[Test]
    public function it_reads_a_map_of_booleans(): void
    {
        self::assertSame(
            ['a' => true, 'b' => false, 'c' => false],
            Payload::of(['flags' => ['a' => true, 'b' => false, 'c' => 'true']])->boolMap('flags'),
        );
    }

    /**
     * @param  callable(Payload, string): array<array-key, mixed>  $read
     */
    #[Test]
    #[DataProvider('mapReaders')]
    public function a_missing_map_is_empty(callable $read): void
    {
        self::assertSame([], $read(Payload::of([]), 'absent'));
        self::assertSame([], $read(Payload::of(['absent' => 'not a map']), 'absent'));
    }

    #[Test]
    public function a_map_entry_of_the_wrong_type_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "settings" to be a map of integers, got string.');

        Payload::of(['settings' => ['wins' => 'seven']])->intMap('settings');
    }

    #[Test]
    public function a_string_map_entry_of_the_wrong_type_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "metadata" to be a map of strings, got array.');

        Payload::of(['metadata' => ['team_name' => ['nested']]])->stringMap('metadata');
    }

    #[Test]
    public function a_number_map_entry_of_the_wrong_type_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "players_points" to be a map of numbers, got bool.');

        Payload::of(['players_points' => ['788' => true]])->floatMap('players_points');
    }

    #[Test]
    public function it_reads_every_item_of_a_number_list(): void
    {
        self::assertSame(
            [10.04, 43.1, 0.0],
            Payload::of(['starters_points' => [10.04, 43.1, 0]])->floats('starters_points'),
        );
    }

    #[Test]
    public function a_missing_number_list_is_empty(): void
    {
        self::assertSame([], Payload::of([])->floats('starters_points'));
        self::assertSame([], Payload::of(['starters_points' => 'nope'])->floats('starters_points'));
    }

    #[Test]
    public function a_non_numeric_item_in_a_number_list_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "starters_points" to be a list of numbers, got string.');

        Payload::of(['starters_points' => [10.04, '43.1']])->floats('starters_points');
    }

    #[Test]
    public function it_reads_a_nested_object(): void
    {
        $settings = Payload::of(['settings' => ['wins' => 7, 'fpts' => 1776]])->object('settings');

        self::assertSame(7, $settings->int('wins'));
        self::assertSame(1776, $settings->int('fpts'));
    }

    #[Test]
    public function a_missing_nested_object_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "settings" to be an object, got null.');

        Payload::of([])->object('settings');
    }

    #[Test]
    public function a_nested_object_of_the_wrong_type_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "settings" to be an object, got string.');

        Payload::of(['settings' => 'nope'])->object('settings');
    }

    /**
     * Absent and explicitly null are the same answer, because Sleeper uses both
     * for "there is nothing here" -- a transaction that is not a waiver sends
     * `settings: null`, and a first-round bracket match omits `t1_from`.
     */
    #[Test]
    public function an_optional_nested_object_tolerates_absence(): void
    {
        self::assertNull(Payload::of([])->nullableObject('settings'));
        self::assertNull(Payload::of(['settings' => null])->nullableObject('settings'));
        self::assertNull(Payload::of(['settings' => 7])->nullableObject('settings'));
        self::assertInstanceOf(Payload::class, Payload::of(['settings' => []])->nullableObject('settings'));
    }

    #[Test]
    public function it_reads_every_object_of_a_list(): void
    {
        $objects = Payload::of(['items' => [['id' => 1], ['id' => 2], ['id' => 3]]])->objects('items');

        self::assertCount(3, $objects);
        self::assertSame([1, 2, 3], array_map(static fn (Payload $item): int => $item->int('id'), $objects));
    }

    #[Test]
    public function a_missing_object_list_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "items" to be an array, got null.');

        Payload::of([])->objects('items');
    }

    #[Test]
    public function a_scalar_item_in_an_object_list_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "items" to be an array of objects, got string.');

        Payload::of(['items' => ['nope']])->objects('items');
    }

    #[Test]
    public function it_reads_every_entry_of_an_object_map(): void
    {
        $map = Payload::of(['players' => ['2315' => ['n' => 1], 'DET' => ['n' => 2]]])->objectMap('players');

        // PHP casts the numeric key to an int; a team defense stays a string.
        // Sleeper's player map contains both, which is why the key type here
        // cannot honestly be narrowed to string.
        self::assertSame([2315, 'DET'], array_keys($map));
        self::assertSame(2, $map['DET']->int('n'));
    }

    #[Test]
    public function a_missing_object_map_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "players" to be an object, got null.');

        Payload::of([])->objectMap('players');
    }

    #[Test]
    public function a_scalar_entry_in_an_object_map_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected "players" to be an object of objects, got int.');

        Payload::of(['players' => ['2315' => 5]])->objectMap('players');
    }

    #[Test]
    public function it_exposes_its_own_keys_and_membership(): void
    {
        $payload = Payload::of(['a' => 1, 2315 => 2, 'c' => null]);

        self::assertSame(['a', '2315', 'c'], $payload->keys());
        self::assertTrue($payload->has('a'));
        // has() is isset-based, so an explicit null reads as absent.
        self::assertFalse($payload->has('c'));
        self::assertFalse($payload->has('missing'));
    }

    #[Test]
    public function a_non_object_response_body_is_an_error(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected the response body to be a JSON object.');

        Payload::fromResponse($this->responseWithBody('"just a string"'));
    }

    #[Test]
    public function it_reads_an_object_response_body(): void
    {
        $payload = Payload::fromResponse($this->responseWithBody('{"sport":"nfl"}'));

        self::assertSame('nfl', $payload->string('sport'));
    }

    /**
     * A username nobody has answers 200 with a body of literal `null`, which is
     * an absence rather than the malformed body `fromResponse` would call it.
     */
    #[Test]
    public function a_null_response_body_reads_as_absent(): void
    {
        self::assertNull(Payload::fromResponseOrNull($this->responseWithBody('null')));
    }

    #[Test]
    public function a_present_response_body_still_reads_through_the_nullable_form(): void
    {
        $payload = Payload::fromResponseOrNull($this->responseWithBody('{"username":"sleeperbot"}'));

        self::assertInstanceOf(Payload::class, $payload);
        self::assertSame('sleeperbot', $payload->string('username'));
    }

    /**
     * Only the exact body counts as absent. Anything else that cannot be read
     * is still an error, so a shape change cannot masquerade as a miss.
     */
    #[Test]
    public function a_body_that_is_merely_unreadable_is_not_treated_as_absent(): void
    {
        $this->expectException(UnexpectedPayloadException::class);
        $this->expectExceptionMessage('Expected the response body to be a JSON object.');

        Payload::fromResponseOrNull($this->responseWithBody('"null"'));
    }

    private function responseWithBody(string $body): Response
    {
        return new SleeperConnector()->send(
            new StubRequest,
            new MockClient([MockResponse::make($body)]),
        );
    }
}
