<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Tests\Requests;

use Fantasyphp\Sleeper\Data\Api\TradedPick;
use Fantasyphp\Sleeper\Data\Api\Transaction;
use Fantasyphp\Sleeper\Data\Api\WaiverBudgetTransfer;
use Fantasyphp\Sleeper\Data\Infrastructure\Payload;
use Fantasyphp\Sleeper\Data\Infrastructure\PayloadList;
use Fantasyphp\Sleeper\Enums\TransactionStatus;
use Fantasyphp\Sleeper\Enums\TransactionType;
use Fantasyphp\Sleeper\Requests\GetTransactionsRequest;
use Fantasyphp\Sleeper\SleeperConnector;
use Fantasyphp\Sleeper\Tests\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

#[CoversClass(GetTransactionsRequest::class)]
#[CoversClass(Transaction::class)]
#[CoversClass(TradedPick::class)]
#[CoversClass(WaiverBudgetTransfer::class)]
#[CoversClass(TransactionType::class)]
#[CoversClass(TransactionStatus::class)]
#[CoversClass(PayloadList::class)]
#[CoversClass(Payload::class)]
#[CoversClass(SleeperConnector::class)]
final class GetTransactionsRequestTest extends RequestTestCase
{
    private const string LEAGUE_ID = '289646328504385536';

    /**
     * A dynasty league, because only a dynasty league trades draft picks.
     */
    private const string DYNASTY_LEAGUE_ID = '337383787396628480';

    /**
     * A league running FAAB waivers, because only those move budget in trades.
     */
    private const string FAAB_LEAGUE_ID = '423022503628124160';

    #[Test]
    public function it_builds_the_transactions_path_for_the_week(): void
    {
        self::assertSame(
            'https://api.sleeper.app/v1/league/289646328504385536/transactions/15',
            $this->uriFor(new GetTransactionsRequest(self::LEAGUE_ID, 15)),
        );
    }

    #[Test]
    public function it_sends_no_query_string(): void
    {
        $request = new GetTransactionsRequest(self::LEAGUE_ID, 15);

        self::assertSame('', $this->queryFor($request));
        self::assertSame([], $this->queryParametersFor($request));
    }

    #[Test]
    public function a_recorded_response_hydrates_every_mapped_field(): void
    {
        $transaction = $this->transactions(self::LEAGUE_ID, 15, 'league/transactions')[0];

        self::assertSame('380967774434091008', $transaction->transactionId);
        self::assertSame('waiver', $transaction->type);
        self::assertSame('complete', $transaction->status);
        self::assertSame('667279356739584', $transaction->creatorUserId);
        self::assertSame(15, $transaction->leg);
        self::assertSame(1545192304765, $transaction->created);
        self::assertSame(1545206698488, $transaction->statusUpdated);
        self::assertSame([3], $transaction->rosterIds);
        self::assertSame([3], $transaction->consenterIds);
        self::assertSame(0, $transaction->settings['waiver_bid']);
        self::assertSame([], $transaction->draftPicks);
        self::assertSame([], $transaction->waiverBudget);
    }

    /**
     * A failed claim is still a transaction and still comes back. Treating the
     * `adds` map as having happened without checking the status would credit a
     * team with a player somebody else won.
     */
    #[Test]
    public function failed_claims_are_returned_alongside_successful_ones(): void
    {
        $transactions = $this->transactions(self::LEAGUE_ID, 15, 'league/transactions');

        $statuses = array_unique(array_map(
            static fn (Transaction $transaction): string => $transaction->status,
            $transactions,
        ));

        sort($statuses);

        self::assertSame(['complete', 'failed'], $statuses);
        self::assertSame(TransactionStatus::Complete, $transactions[0]->state());
        self::assertSame(TransactionType::Waiver, $transactions[0]->type());
    }

    /**
     * `adds` and `drops` are keyed by player and valued by roster, which is the
     * only way one map can describe a four-player trade between two teams.
     */
    #[Test]
    public function adds_and_drops_map_a_player_to_the_roster_that_gets_them(): void
    {
        $trade = $this->tradeIn($this->transactions(
            self::DYNASTY_LEAGUE_ID,
            3,
            'league/transactions-with-picks',
        ));

        self::assertSame(TransactionType::Trade, $trade->type());
        self::assertSame([4, 5], $trade->rosterIds);
        self::assertSame(5, $trade->adds[2378]);
        self::assertSame(4, $trade->drops[2378]);
    }

    /**
     * A dynasty trade can move picks for seasons that have not happened yet,
     * and every ID on one is a roster ID despite two of them being called
     * "owner".
     */
    #[Test]
    public function a_trade_can_carry_draft_picks(): void
    {
        $trade = $this->tradeIn($this->transactions(
            self::DYNASTY_LEAGUE_ID,
            3,
            'league/transactions-with-picks',
        ));

        self::assertCount(2, $trade->draftPicks);

        $pick = $trade->draftPicks[0];

        self::assertSame('2019', $pick->season);
        self::assertSame(3, $pick->round);
        self::assertSame(4, $pick->rosterId);
        self::assertSame(5, $pick->ownerId);
        self::assertSame(4, $pick->previousOwnerId);
        // Present in this shape but never populated.
        self::assertNull($pick->leagueId);
        self::assertNull($pick->draftId);
    }

    #[Test]
    public function a_trade_can_carry_waiver_budget(): void
    {
        $trade = $this->tradeIn($this->transactions(
            self::FAAB_LEAGUE_ID,
            3,
            'league/transactions-with-faab',
        ));

        self::assertCount(1, $trade->waiverBudget);

        $transfer = $trade->waiverBudget[0];

        self::assertSame(6, $transfer->senderRosterId);
        self::assertSame(10, $transfer->receiverRosterId);
        self::assertSame(70, $transfer->amount);
    }

    /**
     * The winning bid lives in `settings`, which is null on a transaction type
     * that has no settings at all.
     */
    #[Test]
    public function a_waiver_bid_is_read_out_of_the_settings(): void
    {
        $transactions = $this->transactions(self::LEAGUE_ID, 15, 'league/transactions');

        $bids = array_filter(array_map(
            static fn (Transaction $transaction): ?int => $transaction->waiverBid(),
            $transactions,
        ), static fn (?int $bid): bool => $bid !== null);

        self::assertNotSame([], $bids);
    }

    #[Test]
    public function a_transaction_with_no_settings_has_no_bid(): void
    {
        $transaction = Transaction::fromPayload(Payload::of([
            'transaction_id' => '1',
            'type' => 'free_agent',
            'status' => 'complete',
            'settings' => null,
            'metadata' => null,
            'adds' => null,
            'drops' => null,
            'draft_picks' => [],
            'waiver_budget' => [],
        ]));

        self::assertNull($transaction->waiverBid());
        self::assertSame([], $transaction->settings);
        self::assertSame([], $transaction->metadata);
        self::assertSame([], $transaction->adds);
        self::assertSame([], $transaction->drops);
        self::assertSame([], $transaction->rosterIds);
        self::assertSame([], $transaction->consenterIds);
        self::assertSame(0, $transaction->leg);
        self::assertSame(0, $transaction->created);
        self::assertSame(0, $transaction->statusUpdated);
        self::assertNull($transaction->creatorUserId);
    }

    /**
     * The timestamps are epoch milliseconds. Handing either straight to a date
     * function lands tens of thousands of years out, so both are converted in
     * one place.
     */
    #[Test]
    public function the_timestamps_are_milliseconds_and_are_converted_as_such(): void
    {
        $transaction = $this->transactions(self::LEAGUE_ID, 15, 'league/transactions')[0];

        self::assertSame('2018-12-19', $transaction->createdAt()->format('Y-m-d'));
        self::assertSame('2018-12-19', $transaction->statusUpdatedAt()->format('Y-m-d'));
        self::assertSame(1545192304, $transaction->createdAt()->getTimestamp());
        self::assertSame(1545206698, $transaction->statusUpdatedAt()->getTimestamp());
    }

    #[Test]
    public function an_unrecognised_type_or_status_reads_as_null(): void
    {
        $transaction = Transaction::fromPayload(Payload::of([
            'transaction_id' => '1',
            'type' => 'auction',
            'status' => 'reticulating',
            'draft_picks' => [],
            'waiver_budget' => [],
        ]));

        self::assertSame('auction', $transaction->type);
        self::assertNull($transaction->type());
        self::assertNull($transaction->state());
    }

    #[Test]
    public function the_endpoint_method_returns_typed_transactions(): void
    {
        $connector = new SleeperConnector;
        $mockClient = new MockClient([
            MockResponse::make([[
                'transaction_id' => '1',
                'type' => 'waiver',
                'status' => 'complete',
                'settings' => ['waiver_bid' => 12],
                'draft_picks' => [],
                'waiver_budget' => [],
            ]]),
        ]);
        $connector->withMockClient($mockClient);

        $transactions = $connector->transactions(self::LEAGUE_ID, 15);

        self::assertCount(1, $transactions);
        self::assertSame(12, $transactions[0]->waiverBid());

        $pendingRequest = $mockClient->getLastPendingRequest();

        self::assertNotNull($pendingRequest);
        self::assertSame([], $pendingRequest->query()->all());
    }

    /**
     * @param  list<Transaction>  $transactions
     */
    private function tradeIn(array $transactions): Transaction
    {
        foreach ($transactions as $transaction) {
            if ($transaction->type() === TransactionType::Trade) {
                return $transaction;
            }
        }

        self::fail('The recorded week was expected to contain a trade.');
    }

    /**
     * @return list<Transaction>
     */
    private function transactions(string $leagueId, int $round, string $fixture): array
    {
        $transactions = $this->dtoFrom(new GetTransactionsRequest($leagueId, $round), $fixture);

        self::assertIsArray($transactions);
        self::assertNotSame([], $transactions);

        $typed = [];

        foreach ($transactions as $transaction) {
            self::assertInstanceOf(Transaction::class, $transaction);
            $typed[] = $transaction;
        }

        return $typed;
    }
}
