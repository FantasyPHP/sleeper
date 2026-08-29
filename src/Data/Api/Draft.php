<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use DateTimeImmutable;
use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Enums\DraftStatus;
use Fantasyphp\Sleeper\Enums\DraftType;
use Fantasyphp\Sleeper\Enums\SeasonType;
use Fantasyphp\Sleeper\Enums\Sport;

/**
 * One draft's configuration.
 *
 * A league can have more than one -- a dynasty league drafts rookies every
 * season -- and `/league/{id}/drafts` returns them most recent first, which is
 * why the singular `draft_id` on a `League` is only the latest one.
 *
 * There are two ways into the same information and they are keyed differently.
 * `draftOrder` maps *user* IDs to draft slots; `slotToRosterId` maps draft
 * slots to *roster* IDs. Going from a slot to the person who drafted from it
 * means both.
 *
 * They fill in at different times and from different routes. Before a draft is
 * drawn, `draft_order` is `null` while `slot_to_roster_id` is already a full
 * map. And `slot_to_roster_id` is only ever sent by `/draft/{id}` -- the same
 * draft fetched through `/league/{id}/drafts` omits the key entirely, for a
 * finished draft as much as a pending one. If you need it, ask for the draft by
 * ID; the list route's copy will always look empty.
 *
 * `settings` is left as a flat map for the same reason a league's is: it mixes
 * per-format keys (`slots_qb`, `slots_bn`, `alpha_sort`) that only appear when
 * they apply.
 *
 * Timestamps are epoch milliseconds, like a transaction's.
 */
final readonly class Draft implements ApiDataContract
{
    /**
     * @param  list<string>  $creatorUserIds
     * @param  array<array-key, int>  $draftOrder  user ID to draft slot
     * @param  array<array-key, int>  $slotToRosterId  draft slot to roster ID
     * @param  array<array-key, int>  $settings
     * @param  array<array-key, string>  $metadata  `name`, `description`, `scoring_type`
     */
    public function __construct(
        public string $draftId,
        public ?string $leagueId,
        public string $sport,
        public string $season,
        public string $seasonType,
        public string $status,
        public string $type,
        public int $created,
        public ?int $startTime,
        public ?int $lastPicked,
        public array $creatorUserIds,
        public array $draftOrder,
        public array $slotToRosterId,
        public array $settings,
        public array $metadata,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            draftId: $payload->string('draft_id'),
            leagueId: $payload->nullableString('league_id'),
            sport: $payload->string('sport'),
            season: $payload->string('season'),
            seasonType: $payload->string('season_type'),
            status: $payload->string('status'),
            type: $payload->string('type'),
            created: $payload->nullableInt('created') ?? 0,
            startTime: $payload->nullableInt('start_time'),
            lastPicked: $payload->nullableInt('last_picked'),
            creatorUserIds: $payload->strings('creators'),
            draftOrder: $payload->intMap('draft_order'),
            slotToRosterId: $payload->intMap('slot_to_roster_id'),
            settings: $payload->intMap('settings'),
            metadata: $payload->stringMap('metadata'),
        );
    }

    public function state(): ?DraftStatus
    {
        return DraftStatus::tryFrom($this->status);
    }

    public function order(): ?DraftType
    {
        return DraftType::tryFrom($this->type);
    }

    public function type(): ?SeasonType
    {
        return SeasonType::tryFrom($this->seasonType);
    }

    public function sport(): ?Sport
    {
        return Sport::tryFrom($this->sport);
    }

    /**
     * When the draft was scheduled to begin, or null for one nobody has
     * scheduled. Converted from the milliseconds Sleeper sends.
     */
    public function startsAt(): ?DateTimeImmutable
    {
        if ($this->startTime === null) {
            return null;
        }

        return new DateTimeImmutable('@'.intdiv($this->startTime, 1000));
    }
}
