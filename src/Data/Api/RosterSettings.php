<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;

/**
 * A roster's record and season point totals.
 *
 * Sleeper stores every points total as two integers rather than one number:
 * `fpts: 1776` alongside `fpts_decimal: 6` means 1776.06. The decimal half is
 * hundredths, not tenths -- values up to 98 appear in the same league, and
 * scores themselves are quoted to two places everywhere else. It is omitted
 * entirely rather than sent as 0 when a total lands on a whole number.
 *
 * `maximumPoints` (`ppts`) is the score the roster would have posted with a
 * perfect lineup every week. Not every league computes it, so it is nullable
 * where the other two are not.
 */
final readonly class RosterSettings implements ApiDataContract
{
    public function __construct(
        public int $wins,
        public int $losses,
        public int $ties,
        public float $pointsFor,
        public float $pointsAgainst,
        public ?float $maximumPoints,
        public int $waiverPosition,
        public int $waiverBudgetUsed,
        public int $totalMoves,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            wins: $payload->nullableInt('wins') ?? 0,
            losses: $payload->nullableInt('losses') ?? 0,
            ties: $payload->nullableInt('ties') ?? 0,
            pointsFor: self::points($payload, 'fpts') ?? 0.0,
            pointsAgainst: self::points($payload, 'fpts_against') ?? 0.0,
            maximumPoints: self::points($payload, 'ppts'),
            waiverPosition: $payload->nullableInt('waiver_position') ?? 0,
            waiverBudgetUsed: $payload->nullableInt('waiver_budget_used') ?? 0,
            totalMoves: $payload->nullableInt('total_moves') ?? 0,
        );
    }

    /**
     * Put a split points total back together.
     *
     * The only place in this client that knows the two halves are two halves.
     * A brand-new league sends `fpts: 0` with no `fpts_decimal` at all, so a
     * missing fractional part counts as zero rather than as a missing total.
     */
    private static function points(Payload $payload, string $key): ?float
    {
        $whole = $payload->nullableInt($key);

        if ($whole === null) {
            return null;
        }

        return $whole + ($payload->nullableInt($key.'_decimal') ?? 0) / 100;
    }
}
