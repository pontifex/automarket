<?php

declare(strict_types=1);

namespace AutoMarket\Domain\Finance\Service;

use AutoMarket\Domain\Finance\Model\ReconciliationDiscrepancy;
use AutoMarket\Domain\Finance\Model\ReconciliationReport;
use AutoMarket\Domain\Shared\ValueObject\Currency;
use AutoMarket\Domain\Shared\ValueObject\Money;
use DateTimeImmutable;

final class ReconciliationEngine
{
    /**
     * @param array<int, array{reference_id: string, amount_cents: int, currency: string, status?: string}> $externalStatement
     * @param array<int, array{reference_id: string, amount_cents: int, currency: string}> $internalTransactions
     */
    public function reconcile(
        array $externalStatement,
        array $internalTransactions,
        Currency $currency = Currency::PLN
    ): ReconciliationReport {
        $report = new ReconciliationReport(
            reconciledAt: new DateTimeImmutable(),
            currency: $currency
        );

        $externalMap = [];
        foreach ($externalStatement as $ext) {
            $externalMap[$ext['reference_id']] = $ext;
        }

        $internalMap = [];
        foreach ($internalTransactions as $int) {
            $internalMap[$int['reference_id']] = $int;
        }

        // 1. Process all external records
        foreach ($externalMap as $refId => $extRecord) {
            $extAmount = Money::fromCents($extRecord['amount_cents'], Currency::from($extRecord['currency']));

            if (!isset($internalMap[$refId])) {
                // In gateway statement, but missing from internal ledger!
                $report->recordDiscrepancy(
                    ReconciliationDiscrepancy::missingInternal($refId, $extAmount)
                );
                continue;
            }

            $intRecord = $internalMap[$refId];
            $intAmount = Money::fromCents($intRecord['amount_cents'], Currency::from($intRecord['currency']));

            if ($extAmount->equals($intAmount)) {
                // Perfect Match!
                $report->recordMatch($extAmount->amountInCents);
            } else {
                // Amount discrepancy
                $report->recordDiscrepancy(
                    ReconciliationDiscrepancy::amountMismatch($refId, $extAmount, $intAmount)
                );
            }
        }

        // 2. Identify records that exist internally but are missing from external statement
        foreach ($internalMap as $refId => $intRecord) {
            if (!isset($externalMap[$refId])) {
                $intAmount = Money::fromCents($intRecord['amount_cents'], Currency::from($intRecord['currency']));
                $report->recordDiscrepancy(
                    ReconciliationDiscrepancy::missingExternal($refId, $intAmount)
                );
            }
        }

        return $report;
    }
}
