<?php

declare(strict_types=1);

use AutoMarket\Domain\Finance\Exception\UnbalancedTransactionException;
use AutoMarket\Domain\Finance\Model\Entry;
use AutoMarket\Domain\Finance\Model\Transaction;
use AutoMarket\Domain\Shared\ValueObject\Currency;
use AutoMarket\Domain\Shared\ValueObject\IdempotencyKey;
use AutoMarket\Domain\Shared\ValueObject\Money;

test('it successfully creates balanced double-entry transaction', function () {
    $amount = Money::fromMajor(250.00, Currency::PLN);

    // Dealer tops up wallet:
    // Debit PAYMENT_GATEWAY_SETTLEMENT (Platform receives money)
    // Credit DEALER_WALLET (Platform owes dealer internal balance)
    $entries = [
        Entry::debit('acc-gateway-settlement', $amount, 'Payment gateway collection'),
        Entry::credit('acc-dealer-wallet', $amount, 'Dealer wallet topup credited'),
    ];

    $tx = new Transaction(
        id: 'tx-12345',
        idempotencyKey: IdempotencyKey::fromString('idem-wallet-topup-001'),
        referenceType: 'WALLET_TOPUP',
        referenceId: 'order-9988',
        entries: $entries
    );

    expect($tx->id)->toBe('tx-12345')
        ->and($tx->getCurrency())->toBe(Currency::PLN)
        ->and($tx->getTotalAmountInCents())->toBe(25000)
        ->and($tx->getEntries())->toHaveCount(2);
});

test('it throws UnbalancedTransactionException when debits do not match credits', function () {
    $debitAmount = Money::fromMajor(100.00, Currency::PLN);
    $creditAmount = Money::fromMajor(90.00, Currency::PLN);

    $entries = [
        Entry::debit('acc-1', $debitAmount, 'Debit entry'),
        Entry::credit('acc-2', $creditAmount, 'Credit entry'),
    ];

    new Transaction(
        id: 'tx-unbalanced',
        idempotencyKey: IdempotencyKey::fromString('idem-unbalanced'),
        referenceType: 'TEST',
        referenceId: 'ref-1',
        entries: $entries
    );
})->throws(UnbalancedTransactionException::class);

test('it throws exception when transaction has fewer than 2 entries', function () {
    $amount = Money::fromMajor(50.00, Currency::PLN);

    new Transaction(
        id: 'tx-single',
        idempotencyKey: IdempotencyKey::fromString('idem-single'),
        referenceType: 'TEST',
        referenceId: 'ref-1',
        entries: [Entry::debit('acc-1', $amount, 'Lonely debit')]
    );
})->throws(InvalidArgumentException::class, 'must contain at least 2 entries');
