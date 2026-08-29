<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

use Fantasyphp\Sleeper\Data\Contracts\ApiDataContract;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;

/**
 * FAAB moving between two teams as part of a trade.
 *
 * Both IDs are roster IDs. The amount is in whatever unit the league's
 * `waiver_budget` setting is denominated in -- usually 100 for a whole season.
 */
final readonly class WaiverBudgetTransfer implements ApiDataContract
{
    public function __construct(
        public int $senderRosterId,
        public int $receiverRosterId,
        public int $amount,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            senderRosterId: $payload->int('sender'),
            receiverRosterId: $payload->int('receiver'),
            amount: $payload->int('amount'),
        );
    }
}
