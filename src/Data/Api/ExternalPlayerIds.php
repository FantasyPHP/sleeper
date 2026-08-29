<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;

/**
 * The same player's ID at the other services Sleeper cross-references.
 *
 * This is most of the reason to pull the player map at all: a roster gives you
 * Sleeper's IDs and nothing else, so joining Sleeper to any other data source
 * runs through here.
 *
 * All of them are sparse, and how sparse varies wildly -- `sportradar_id` is on
 * almost every player, `stats_id` on about a quarter. A team defense has none
 * of them.
 *
 * Sleeper carries several more (`kalshi_id`, `oddsjam_id`, `swish_id`,
 * `opta_id`, `pandascore_id`) that are either always null for the NFL or belong
 * to betting rather than fantasy providers; they are not modelled.
 */
final readonly class ExternalPlayerIds implements ApiDataContract
{
    public function __construct(
        public ?int $espn,
        public ?int $yahoo,
        public ?string $sportradar,
        public ?int $rotowire,
        public ?int $rotoworld,
        public ?int $fantasyData,
        public ?string $gsis,
        public ?int $stats,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            espn: $payload->nullableInt('espn_id'),
            yahoo: $payload->nullableInt('yahoo_id'),
            sportradar: $payload->nullableString('sportradar_id'),
            rotowire: $payload->nullableInt('rotowire_id'),
            rotoworld: $payload->nullableInt('rotoworld_id'),
            fantasyData: $payload->nullableInt('fantasy_data_id'),
            gsis: $payload->nullableString('gsis_id'),
            stats: $payload->nullableInt('stats_id'),
        );
    }
}
