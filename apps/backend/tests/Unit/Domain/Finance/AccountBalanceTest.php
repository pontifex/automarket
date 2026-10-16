<?php

declare(strict_types=1);

use AutoMarket\Domain\Finance\Exception\InsufficientFundsException;
use AutoMarket\Domain\Finance\Model\Account;
use AutoMarket\Domain\Finance\Model\AccountType;
use AutoMarket\Domain\Finance\Model\Entry;
use AutoMarket\Domain\Shared\ValueObject\Currency;
use AutoMarket\Domain\Shared\ValueObject\Money;

test('dealer wallet increases balance on credit and decreases on debit', function () {
    $wallet = new Account(
        id: 'acc-dealer-1',
        type: AccountType::DEALER_WALLET,
        currency: Currency::PLN,
        ownerId: 'dealer-42'
    );

    expect($wallet->getBalanceInCents())->toBe(0);

    // Credit 500 PLN (Topup)
    $wallet->applyEntry(Entry::credit('acc-dealer-1', Money::fromMajor(500, Currency::PLN), 'Topup'));
    expect($wallet->getBalanceInCents())->toBe(50000)
        ->and($wallet->getBalance()->toMajor())->toBe(500.00);

    // Debit 100 PLN (Pay for listing promotion)
    $wallet->applyEntry(Entry::debit('acc-dealer-1', Money::fromMajor(100, Currency::PLN), 'Promotion fee'));
    expect($wallet->getBalanceInCents())->toBe(40000)
        ->and($wallet->getBalance()->toMajor())->toBe(400.00);
});

test('dealer wallet throws InsufficientFundsException when debited more than balance', function () {
    $wallet = new Account(
        id: 'acc-dealer-2',
        type: AccountType::DEALER_WALLET,
        currency: Currency::PLN,
        ownerId: 'dealer-99'
    );

    // Topup 50 PLN
    $wallet->applyEntry(Entry::credit('acc-dealer-2', Money::fromMajor(50, Currency::PLN), 'Topup'));

    // Try to spend 100 PLN
    $wallet->applyEntry(Entry::debit('acc-dealer-2', Money::fromMajor(100, Currency::PLN), 'Overdraft attempt'));
})->throws(InsufficientFundsException::class);
