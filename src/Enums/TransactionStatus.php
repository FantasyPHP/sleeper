<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Enums;

/**
 * Whether a transaction went through.
 *
 * A failed transaction is still returned by the endpoint, which is the point:
 * a losing waiver claim is a fact about the league's week, not an error. The
 * reason it failed is in the transaction's `metadata.notes`.
 */
enum TransactionStatus: string
{
    case Complete = 'complete';

    case Failed = 'failed';

    case Pending = 'pending';
}
