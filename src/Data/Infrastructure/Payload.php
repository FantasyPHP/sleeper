<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Infrastructure;

use Fantasyphp\Sleeper\Exceptions\UnexpectedPayloadException;
use Saloon\Http\Response;

/**
 * Typed reader over one decoded JSON object.
 *
 * The API is loose about scalar types. `week` arrives as an integer while
 * `season` next to it arrives as a numeric string, a draft pick's `metadata`
 * sends every number as a string including the player ID that is an integer
 * elsewhere, and half the fields on a team defense are simply absent rather
 * than null. Reading through here keeps the coercion in one place instead of
 * scattered across every DTO.
 */
final readonly class Payload
{
    /**
     * @param  array<array-key, mixed>  $values
     */
    private function __construct(private array $values) {}

    /**
     * @param  array<array-key, mixed>  $values
     */
    public static function of(array $values): self
    {
        return new self($values);
    }

    /**
     * Decodes the raw body rather than calling Response::json(), whose phpdoc
     * promises an array. json_decode doesn't keep that promise for a scalar
     * body, so the guard below would be unverifiable.
     */
    public static function fromResponse(Response $response): self
    {
        $decoded = json_decode($response->body(), true);

        if (! is_array($decoded)) {
            throw UnexpectedPayloadException::notAnObject();
        }

        return new self($decoded);
    }

    /**
     * The same read, for the endpoints where "no such thing" is not an error.
     *
     * Looking a user up by a username nobody has answers `200` with a body of
     * literal `null` rather than the 404 the docs would lead you to expect, so
     * a miss has to be told apart from a body that is merely the wrong shape.
     */
    public static function fromResponseOrNull(Response $response): ?self
    {
        // Matched against the raw body rather than a decode, so "the API said
        // null" stays distinguishable from "the API said something this can't
        // read". Sleeper sends the four bytes and nothing else, no whitespace.
        if ($response->body() === 'null') {
            return null;
        }

        return self::fromResponse($response);
    }

    public function has(string $key): bool
    {
        return isset($this->values[$key]);
    }

    /**
     * The object's own keys, for payloads that use IDs as keys.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(strval(...), array_keys($this->values));
    }

    public function string(string $key): string
    {
        return $this->nullableString($key)
            ?? throw UnexpectedPayloadException::expected('a string', $key, $this->values[$key] ?? null);
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->values[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    public function int(string $key): int
    {
        return $this->nullableInt($key)
            ?? throw UnexpectedPayloadException::expected('an integer', $key, $this->values[$key] ?? null);
    }

    /**
     * Numeric strings count, since seasons arrive as `"2018"` and a draft
     * pick's metadata sends every number that way.
     */
    public function nullableInt(string $key): ?int
    {
        $value = $this->values[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Numeric strings count, for the same reason `nullableInt` accepts them.
     * Note "0" is a real value (a starter who scored nothing all week) and
     * must not collapse to null.
     */
    public function nullableFloat(string $key): ?float
    {
        $value = $this->values[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    public function bool(string $key): bool
    {
        return ($this->values[$key] ?? null) === true;
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        $value = $this->values[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw UnexpectedPayloadException::expected('a list of strings', $key, $item);
            }

            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * @return list<int>
     */
    public function ints(string $key): array
    {
        $value = $this->values[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $ints = [];

        foreach ($value as $item) {
            if (! is_int($item)) {
                throw UnexpectedPayloadException::expected('a list of integers', $key, $item);
            }

            $ints[] = $item;
        }

        return $ints;
    }

    /**
     * One nested JSON object, read as its own payload.
     *
     * Sleeper nests heavily and inconsistently: a roster's `settings` is a flat
     * map of integers, a draft's `settings` mixes integers with slot counts, and
     * a bracket row's `t1_from` holds a single key that is either `w` or `l`.
     * None of those fit the scalar map readers, and none of them are arrays.
     */
    public function object(string $key): self
    {
        return $this->nullableObject($key)
            ?? throw UnexpectedPayloadException::expected('an object', $key, $this->values[$key] ?? null);
    }

    /**
     * The same read where the key is optional. Absent and `null` are the same
     * answer, because Sleeper uses both for "there is nothing here".
     */
    public function nullableObject(string $key): ?self
    {
        $value = $this->values[$key] ?? null;

        if (! is_array($value)) {
            return null;
        }

        return new self($value);
    }

    /**
     * @return list<float>
     */
    public function floats(string $key): array
    {
        $value = $this->values[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $floats = [];

        foreach ($value as $item) {
            if (! is_int($item) && ! is_float($item)) {
                throw UnexpectedPayloadException::expected('a list of numbers', $key, $item);
            }

            $floats[] = (float) $item;
        }

        return $floats;
    }

    /**
     * A JSON array of objects.
     *
     * @return list<self>
     */
    public function objects(string $key): array
    {
        $value = $this->values[$key] ?? null;

        if (! is_array($value)) {
            throw UnexpectedPayloadException::expected('an array', $key, $value);
        }

        $objects = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                throw UnexpectedPayloadException::expected('an array of objects', $key, $item);
            }

            $objects[] = new self($item);
        }

        return $objects;
    }

    /**
     * A JSON object whose values are scalars, e.g. user ID to draft slot.
     *
     * Keys stay `array-key` for the same reason `objectMap()` does: PHP casts a
     * numeric string key to an int on its own.
     *
     * @return array<array-key, int>
     */
    public function intMap(string $key): array
    {
        return $this->scalarMap(
            $key,
            'a map of integers',
            static fn (self $entries, string $entry): ?int => $entries->nullableInt($entry),
        );
    }

    /**
     * @return array<array-key, float>
     */
    public function floatMap(string $key): array
    {
        return $this->scalarMap(
            $key,
            'a map of numbers',
            static fn (self $entries, string $entry): ?float => $entries->nullableFloat($entry),
        );
    }

    /**
     * @return array<array-key, string>
     */
    public function stringMap(string $key): array
    {
        return $this->scalarMap(
            $key,
            'a map of strings',
            static fn (self $entries, string $entry): ?string => $entries->nullableString($entry),
        );
    }

    /**
     * Only a literal JSON `true` counts, matching `bool()`.
     *
     * @return array<array-key, bool>
     */
    public function boolMap(string $key): array
    {
        $value = $this->values[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach (array_keys($value) as $mapKey) {
            $map[$mapKey] = self::of($value)->bool((string) $mapKey);
        }

        return $map;
    }

    /**
     * A JSON object used as a lookup, e.g. player ID to player details.
     *
     * Keys stay `array-key`, not `string`: PHP silently casts a numeric string
     * key like "2315" to an int, so an `array<string, ...>` claim would be a
     * lie. Sleeper leans on this shape hard -- the player map is keyed by
     * player ID, and most of those IDs are numeric strings.
     *
     * @return array<array-key, self>
     */
    public function objectMap(string $key): array
    {
        $value = $this->values[$key] ?? null;

        if (! is_array($value)) {
            throw UnexpectedPayloadException::expected('an object', $key, $value);
        }

        $map = [];

        foreach ($value as $mapKey => $item) {
            if (! is_array($item)) {
                throw UnexpectedPayloadException::expected('an object of objects', $key, $item);
            }

            $map[$mapKey] = new self($item);
        }

        return $map;
    }

    /**
     * Shared body of the scalar map readers: read every entry through one of the
     * nullable scalar accessors, and reject anything it cannot make sense of
     * rather than silently dropping the entry.
     *
     * @template TValue of int|float|string
     *
     * @param  callable(self, string): ?TValue  $read
     * @return array<array-key, TValue>
     */
    private function scalarMap(string $key, string $expected, callable $read): array
    {
        $value = $this->values[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $entries = self::of($value);
        $map = [];

        foreach (array_keys($value) as $mapKey) {
            $map[$mapKey] = $read($entries, (string) $mapKey)
                ?? throw UnexpectedPayloadException::expected($expected, $key, $value[$mapKey]);
        }

        return $map;
    }
}
