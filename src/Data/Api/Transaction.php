<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use DateTimeImmutable;
use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Enums\TransactionStatus;
use Fantasyphp\Sleeper\Enums\TransactionType;

/**
 * One move in a league: a waiver claim, a free agent pickup, or a trade.
 *
 * Failed transactions come back alongside successful ones and that is on
 * purpose -- a waiver claim somebody lost is a fact about the week. Check
 * `state()` before treating an `add` as having happened, and read
 * `metadata['notes']` for why a failure failed.
 *
 * `adds` and `drops` are keyed by player ID and valued by roster ID, which
 * reads backwards until you see a trade: there, one call has to say which of
 * several teams received which of several players. A one-sided pickup is the
 * same map with one entry.
 *
 * `created` and `statusUpdated` are epoch *milliseconds*, not seconds. Passing
 * either straight to a date function silently lands you tens of thousands of
 * years out, which is what `createdAt()` and `statusUpdatedAt()` are for.
 */
final readonly class Transaction implements ApiDataContract
{
    /**
     * @param  array<array-key, int>  $adds  player ID to the roster receiving them
     * @param  array<array-key, int>  $drops  player ID to the roster giving them up
     * @param  list<int>  $rosterIds  every roster involved
     * @param  list<int>  $consenterIds  the rosters that agreed
     * @param  list<TradedPick>  $draftPicks
     * @param  list<WaiverBudgetTransfer>  $waiverBudget
     * @param  array<array-key, int>  $settings  `waiver_bid` and `priority`, when they apply
     * @param  array<array-key, string>  $metadata
     */
    public function __construct(
        public string $transactionId,
        public string $type,
        public string $status,
        public ?string $creatorUserId,
        public int $leg,
        public int $created,
        public int $statusUpdated,
        public array $adds,
        public array $drops,
        public array $rosterIds,
        public array $consenterIds,
        public array $draftPicks,
        public array $waiverBudget,
        public array $settings,
        public array $metadata,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            transactionId: $payload->string('transaction_id'),
            type: $payload->string('type'),
            status: $payload->string('status'),
            creatorUserId: $payload->nullableString('creator'),
            leg: $payload->nullableInt('leg') ?? 0,
            created: $payload->nullableInt('created') ?? 0,
            statusUpdated: $payload->nullableInt('status_updated') ?? 0,
            adds: $payload->intMap('adds'),
            drops: $payload->intMap('drops'),
            rosterIds: $payload->ints('roster_ids'),
            consenterIds: $payload->ints('consenter_ids'),
            draftPicks: array_map(TradedPick::fromPayload(...), $payload->objects('draft_picks')),
            waiverBudget: array_map(WaiverBudgetTransfer::fromPayload(...), $payload->objects('waiver_budget')),
            settings: $payload->intMap('settings'),
            metadata: $payload->stringMap('metadata'),
        );
    }

    /**
     * The kind of move as an enum, or null for one this client does not know.
     */
    public function type(): ?TransactionType
    {
        return TransactionType::tryFrom($this->type);
    }

    public function state(): ?TransactionStatus
    {
        return TransactionStatus::tryFrom($this->status);
    }

    /**
     * When the move was proposed. Converted from the milliseconds Sleeper
     * sends, so this is the only place the unit has to be remembered.
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->fromMilliseconds($this->created);
    }

    /**
     * When it was settled -- accepted, processed, or rejected. For a waiver
     * claim this is when waivers ran, which can be days after `createdAt()`.
     */
    public function statusUpdatedAt(): DateTimeImmutable
    {
        return $this->fromMilliseconds($this->statusUpdated);
    }

    /**
     * What the winning bidder paid, for a league running FAAB waivers.
     */
    public function waiverBid(): ?int
    {
        return $this->settings['waiver_bid'] ?? null;
    }

    private function fromMilliseconds(int $milliseconds): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.intdiv($milliseconds, 1000));
    }
}
