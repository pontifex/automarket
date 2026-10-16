<?php

declare(strict_types=1);

use AutoMarket\Domain\Finance\Model\ReconciliationStatus;
use AutoMarket\Domain\Finance\Service\ReconciliationEngine;
use AutoMarket\Domain\Shared\ValueObject\Currency;

test('it reports full match when all external and internal transactions align', function () {
    $engine = new ReconciliationEngine();

    $external = [
        ['reference_id' => 'ref_001', 'amount_cents' => 15000, 'currency' => 'PLN'],
        ['reference_id' => 'ref_002', 'amount_cents' => 30000, 'currency' => 'PLN'],
    ];

    $internal = [
        ['reference_id' => 'ref_001', 'amount_cents' => 15000, 'currency' => 'PLN'],
        ['reference_id' => 'ref_002', 'amount_cents' => 30000, 'currency' => 'PLN'],
    ];

    $report = $engine->reconcile($external, $internal, Currency::PLN);

    expect($report->isFullyBalanced())->toBeTrue()
        ->and($report->matchedCount)->toBe(2)
        ->and($report->getDiscrepancyCount())->toBe(0)
        ->and($report->getTotalReconciled()->amountInCents)->toBe(45000);
});

test('it detects missing internal transactions, missing external records, and amount mismatches', function () {
    $engine = new ReconciliationEngine();

    $external = [
        ['reference_id' => 'matched_01', 'amount_cents' => 10000, 'currency' => 'PLN'],
        ['reference_id' => 'missing_in_db', 'amount_cents' => 25000, 'currency' => 'PLN'],
        ['reference_id' => 'price_diff', 'amount_cents' => 50000, 'currency' => 'PLN'],
    ];

    $internal = [
        ['reference_id' => 'matched_01', 'amount_cents' => 10000, 'currency' => 'PLN'],
        ['reference_id' => 'price_diff', 'amount_cents' => 45000, 'currency' => 'PLN'], // mismatch by 50 PLN
        ['reference_id' => 'ghost_internal', 'amount_cents' => 8000, 'currency' => 'PLN'],
    ];

    $report = $engine->reconcile($external, $internal, Currency::PLN);

    expect($report->isFullyBalanced())->toBeFalse()
        ->and($report->matchedCount)->toBe(1)
        ->and($report->getDiscrepancyCount())->toBe(3);

    $discrepancies = collect($report->discrepancies)->keyBy('referenceId');

    // 1. Missing in internal ledger (e.g. lost webhook)
    expect($discrepancies['missing_in_db']->status)->toBe(ReconciliationStatus::MISSING_INTERNAL)
        ->and($discrepancies['missing_in_db']->difference->amountInCents)->toBe(25000);

    // 2. Amount mismatch
    expect($discrepancies['price_diff']->status)->toBe(ReconciliationStatus::AMOUNT_MISMATCH)
        ->and($discrepancies['price_diff']->difference->amountInCents)->toBe(5000);

    // 3. Missing in external gateway
    expect($discrepancies['ghost_internal']->status)->toBe(ReconciliationStatus::MISSING_EXTERNAL)
        ->and($discrepancies['ghost_internal']->difference->amountInCents)->toBe(8000);
});
