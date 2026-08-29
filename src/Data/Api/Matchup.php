<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;

/**
 * One team's side of one week.
 *
 * The endpoint returns a row per team, not per fixture. Two rows sharing a
 * `matchupId` are playing each other; pairing them up is the caller's job and
 * there is no other link between them.
 *
 * `matchupId` is nullable and that is not an edge case -- during the playoffs
 * every eliminated team still gets a row, with points and a lineup and no
 * opponent. Those rows are what a bracket leaves behind.
 *
 * Unlike a roster's season totals, weekly points arrive as one number already.
 * `customPoints` is a commissioner override; when it is set it is the score
 * that counts and `points` is what the scoring rules produced.
 */
final readonly class Matchup implements ApiDataContract
{
    /**
     * @param  list<string>  $starters  in the league's roster-position order
     * @param  list<float>  $starterPoints  index-for-index with $starters
     * @param  list<string>  $players
     * @param  array<array-key, float>  $playerPoints  keyed by player ID
     */
    public function __construct(
        public int $rosterId,
        public ?int $matchupId,
        public float $points,
        public ?float $customPoints,
        public array $starters,
        public array $starterPoints,
        public array $players,
        public array $playerPoints,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            rosterId: $payload->int('roster_id'),
            matchupId: $payload->nullableInt('matchup_id'),
            points: $payload->nullableFloat('points') ?? 0.0,
            customPoints: $payload->nullableFloat('custom_points'),
            starters: $payload->strings('starters'),
            starterPoints: $payload->floats('starters_points'),
            players: $payload->strings('players'),
            playerPoints: $payload->floatMap('players_points'),
        );
    }

    /**
     * The score that decides the week: the override if there is one, otherwise
     * what the scoring rules produced.
     */
    public function score(): float
    {
        return $this->customPoints ?? $this->points;
    }
}
