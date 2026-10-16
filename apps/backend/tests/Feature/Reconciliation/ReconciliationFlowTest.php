<?php

declare(strict_types=1);

use AutoMarket\Application\Finance\Command\ReconcileExternalTransactionsCommand;
use AutoMarket\Application\Finance\Command\ReconcileExternalTransactionsHandler;
use AutoMarket\Application\Finance\Port\PaymentGatewayPort;
use AutoMarket\Domain\Finance\Model\AccountType;
use AutoMarket\Domain\Finance\Model\Entry;
use AutoMarket\Domain\Finance\Model\ReconciliationStatus;
use AutoMarket\Domain\Finance\Model\Transaction;
use AutoMarket\Domain\Finance\Repository\LedgerRepositoryInterface;
use AutoMarket\Domain\Shared\ValueObject\Currency;
use AutoMarket\Domain\Shared\ValueObject\IdempotencyKey;
use AutoMarket\Domain\Shared\ValueObject\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('it executes reconciliation flow, identifies discrepancies and stores audit trail in database', function () {
    /** @var LedgerRepositoryInterface $repo */
    $repo = app(LedgerRepositoryInterface::class);

    // 1. Setup internal ledger with 1 transaction (order_matched_100)
    $settlement = $repo->getOrCreateAccount(null, AccountType::PAYMENT_GATEWAY_SETTLEMENT, Currency::PLN);
    $dealer = $repo->getOrCreateAccount('dealer_vip_01', AccountType::DEALER_WALLET, Currency::PLN);

    $tx1 = new Transaction(
        id: (string) Str::uuid(),
        idempotencyKey: IdempotencyKey::fromString('idem-tx-matched-100'),
        referenceType: 'WALLET_TOPUP',
        referenceId: 'order_matched_100',
        entries: [
            Entry::debit($settlement->id, Money::fromMajor(100, Currency::PLN), 'Settlement'),
            Entry::credit($dealer->id, Money::fromMajor(100, Currency::PLN), 'Topup'),
        ]
    );
    $repo->saveTransaction($tx1);

    // 2. Mock external payment gateway returning 2 items (one matched, one missing internally)
    $fakeGateway = new class implements PaymentGatewayPort {
        public function charge(string $chargeId, Money $amount, string $description, IdempotencyKey $idempotencyKey): \AutoMarket\Application\Finance\Port\PaymentResult
        {
            return \AutoMarket\Application\Finance\Port\PaymentResult::success('ref', $amount);
        }

        public function fetchStatement(DateTimeImmutable $from, DateTimeImmutable $to): array
        {
            return [
                [
                    'reference_id' => 'order_matched_100',
                    'amount_cents' => 10000, // 100.00 PLN (MATCHED)
                    'currency' => 'PLN',
                    'timestamp' => '2026-10-16T08:00:00Z',
                ],
                [
                    'reference_id' => 'order_lost_webhook_250',
                    'amount_cents' => 25000, // 250.00 PLN (MISSING IN DATABASE!)
                    'currency' => 'PLN',
                    'timestamp' => '2026-10-16T08:15:00Z',
                ],
            ];
        }
    };

    $this->app->instance(PaymentGatewayPort::class, $fakeGateway);

    // 3. Execute reconciliation handler
    /** @var ReconcileExternalTransactionsHandler $handler */
    $handler = app(ReconcileExternalTransactionsHandler::class);

    $now = new DateTimeImmutable();
    $command = new ReconcileExternalTransactionsCommand(
        from: $now->modify('-1 hour'),
        to: $now->modify('+1 hour'),
        currency: Currency::PLN
    );

    $report = $handler->handle($command);

    // 4. Assert report correctness
    expect($report->matchedCount)->toBe(1)
        ->and($report->getDiscrepancyCount())->toBe(1)
        ->and($report->isFullyBalanced())->toBeFalse();

    $discrepancy = $report->discrepancies[0];
    expect($discrepancy->referenceId)->toBe('order_lost_webhook_250')
        ->and($discrepancy->status)->toBe(ReconciliationStatus::MISSING_INTERNAL)
        ->and($discrepancy->difference->amountInCents)->toBe(25000);

    // 5. Verify Database Audit Trail
    $dbRun = DB::table('reconciliation_runs')->first();
    expect($dbRun)->not->toBeNull()
        ->and($dbRun->matched_count)->toBe(1)
        ->and($dbRun->discrepancy_count)->toBe(1)
        ->and($dbRun->status)->toBe('DISCREPANCIES_FOUND');

    $dbDiscrepancy = DB::table('reconciliation_discrepancies')->where('run_id', $dbRun->id)->first();
    expect($dbDiscrepancy)->not->toBeNull()
        ->and($dbDiscrepancy->reference_id)->toBe('order_lost_webhook_250')
        ->and((int) $dbDiscrepancy->difference_cents)->toBe(25000);
});
