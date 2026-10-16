<?php

declare(strict_types=1);

namespace App\Console\Commands;

use AutoMarket\Application\Finance\Command\ReconcileExternalTransactionsCommand;
use AutoMarket\Application\Finance\Command\ReconcileExternalTransactionsHandler;
use AutoMarket\Domain\Shared\ValueObject\Currency;
use DateTimeImmutable;
use Illuminate\Console\Command;

final class ReconcileFinanceCommand extends Command
{
    protected $signature = 'finance:reconcile 
                            {--from= : Start datetime (Y-m-d H:i:s, defaults to 24h ago)}
                            {--to= : End datetime (Y-m-d H:i:s, defaults to now)}
                            {--currency=PLN : Currency code (PLN, EUR, USD)}';

    protected $description = 'Executes automated data reconciliation between external payment provider and internal ledger';

    public function handle(ReconcileExternalTransactionsHandler $handler): int
    {
        $now = new DateTimeImmutable();
        $fromStr = $this->option('from') ?: $now->modify('-24 hours')->format('Y-m-d H:i:s');
        $toStr = $this->option('to') ?: $now->format('Y-m-d H:i:s');
        $currencyCode = (string) $this->option('currency');

        $from = new DateTimeImmutable($fromStr);
        $to = new DateTimeImmutable($toStr);
        $currency = Currency::from($currencyCode);

        $this->info(sprintf('Starting finance reconciliation for [%s - %s] (%s)...', $fromStr, $toStr, $currencyCode));

        $command = new ReconcileExternalTransactionsCommand(
            from: $from,
            to: $to,
            currency: $currency
        );

        $report = $handler->handle($command);

        $this->newLine();
        $this->line(sprintf('Matched Transactions:       <info>%d</info> (%s)', $report->matchedCount, $report->getTotalReconciled()->format()));
        $this->line(sprintf('Discrepancies Found:        <comment>%d</comment> (%s)', $report->getDiscrepancyCount(), $report->getTotalDiscrepancy()->format()));

        if ($report->isFullyBalanced()) {
            $this->newLine();
            $this->info('✓ Reconciliation fully balanced. All external and internal records match perfectly.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('⚠ Discrepancies detected between Payment Gateway and Internal Ledger:');

        $rows = [];
        foreach ($report->discrepancies as $d) {
            $rows[] = [
                $d->referenceId,
                $d->status->value,
                $d->externalAmount?->format() ?? '-',
                $d->internalAmount?->format() ?? '-',
                $d->difference->format(),
                $d->details,
            ];
        }

        $this->table(
            ['Reference ID', 'Status', 'Gateway Amount', 'Ledger Amount', 'Difference', 'Details'],
            $rows
        );

        return self::FAILURE;
    }
}
