<?php

declare(strict_types=1);

namespace AutoMarket\Application\Finance\Command;

use AutoMarket\Application\Finance\Port\PaymentGatewayPort;
use AutoMarket\Domain\Finance\Model\ReconciliationReport;
use AutoMarket\Domain\Finance\Model\ReconciliationStatus;
use AutoMarket\Domain\Finance\Repository\LedgerRepositoryInterface;
use AutoMarket\Domain\Finance\Service\ReconciliationEngine;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ReconcileExternalTransactionsHandler
{
    public function __construct(
        private PaymentGatewayPort $paymentGateway,
        private LedgerRepositoryInterface $ledgerRepository,
        private ReconciliationEngine $engine
    ) {}

    public function handle(ReconcileExternalTransactionsCommand $command): ReconciliationReport
    {
        // 1. Fetch statement from external payment provider
        $externalStatement = $this->paymentGateway->fetchStatement($command->from, $command->to);

        // 2. Fetch corresponding internal transactions from ledger
        $internalRows = DB::table('ledger_transactions')
            ->whereBetween('created_at', [
                $command->from->format('Y-m-d H:i:s'),
                $command->to->format('Y-m-d H:i:s'),
            ])
            ->where('currency', $command->currency->value)
            ->get();

        $internalTransactions = [];
        foreach ($internalRows as $row) {
            $internalTransactions[] = [
                'reference_id' => $row->reference_id,
                'amount_cents' => (int) $row->total_cents,
                'currency' => $row->currency,
            ];
        }

        // 3. Execute domain reconciliation logic
        $report = $this->engine->reconcile($externalStatement, $internalTransactions, $command->currency);

        // 4. Persist run and discrepancies for auditing and Filament dashboard
        $runId = (string) Str::uuid();
        $now = new DateTimeImmutable();

        DB::table('reconciliation_runs')->insert([
            'id' => $runId,
            'reconciled_at' => $report->reconciledAt->format('Y-m-d H:i:s'),
            'currency' => $report->currency->value,
            'matched_count' => $report->matchedCount,
            'discrepancy_count' => $report->getDiscrepancyCount(),
            'total_reconciled_cents' => $report->totalReconciledCents,
            'total_discrepancy_cents' => $report->totalDiscrepancyCents,
            'status' => $report->isFullyBalanced() ? 'BALANCED' : 'DISCREPANCIES_FOUND',
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);

        foreach ($report->discrepancies as $discrepancy) {
            DB::table('reconciliation_discrepancies')->insert([
                'id' => (string) Str::uuid(),
                'run_id' => $runId,
                'reference_id' => $discrepancy->referenceId,
                'status' => $discrepancy->status->value,
                'external_amount_cents' => $discrepancy->externalAmount?->amountInCents ?? 0,
                'internal_amount_cents' => $discrepancy->internalAmount?->amountInCents ?? 0,
                'difference_cents' => $discrepancy->difference->amountInCents,
                'currency' => $discrepancy->difference->currency->value,
                'details' => $discrepancy->details,
                'resolved' => false,
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);
        }

        return $report;
    }
}
