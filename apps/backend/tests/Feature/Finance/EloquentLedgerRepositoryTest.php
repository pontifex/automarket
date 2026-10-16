<?php

declare(strict_types=1);

use AutoMarket\Domain\Finance\Exception\InsufficientFundsException;
use AutoMarket\Domain\Finance\Model\AccountType;
use AutoMarket\Domain\Finance\Model\Entry;
use AutoMarket\Domain\Finance\Model\Transaction;
use AutoMarket\Domain\Finance\Repository\LedgerRepositoryInterface;
use AutoMarket\Domain\Shared\ValueObject\Currency;
use AutoMarket\Domain\Shared\ValueObject\IdempotencyKey;
use AutoMarket\Domain\Shared\ValueObject\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('it atomically records double-entry transaction and updates account balances', function () {
    /** @var LedgerRepositoryInterface $repo */
    $repo = app(LedgerRepositoryInterface::class);

    // 1. Create Accounts
    $settlement = $repo->getOrCreateAccount(null, AccountType::PAYMENT_GATEWAY_SETTLEMENT, Currency::PLN);
    $dealerWallet = $repo->getOrCreateAccount('dealer-001', AccountType::DEALER_WALLET, Currency::PLN);

    expect($settlement->getBalanceInCents())->toBe(0)
        ->and($dealerWallet->getBalanceInCents())->toBe(0);

    // 2. Execute Wallet Top-up (500 PLN)
    $amount = Money::fromMajor(500, Currency::PLN);
    $tx = new Transaction(
        id: (string) Str::uuid(),
        idempotencyKey: IdempotencyKey::fromString('idem-topup-500-pln'),
        referenceType: 'WALLET_TOPUP',
        referenceId: 'order-101',
        entries: [
            Entry::debit($settlement->id, $amount, 'Settlement incoming'),
            Entry::credit($dealerWallet->id, $amount, 'Dealer wallet topup credited'),
        ]
    );

    $repo->saveTransaction($tx);

    // 3. Verify Balances in Database
    expect($repo->getAccountBalance($settlement->id)->amountInCents)->toBe(50000)
        ->and($repo->getAccountBalance($dealerWallet->id)->amountInCents)->toBe(50000);

    // 4. Test Idempotency: Repeating save of the SAME transaction should NOT change balances!
    $repo->saveTransaction($tx);

    expect($repo->getAccountBalance($settlement->id)->amountInCents)->toBe(50000)
        ->and($repo->getAccountBalance($dealerWallet->id)->amountInCents)->toBe(50000);
});

test('it rolls back transaction and throws InsufficientFundsException if wallet has no funds', function () {
    /** @var LedgerRepositoryInterface $repo */
    $repo = app(LedgerRepositoryInterface::class);

    $dealerWallet = $repo->getOrCreateAccount('dealer-empty', AccountType::DEALER_WALLET, Currency::PLN);
    $revenue = $repo->getOrCreateAccount(null, AccountType::PLATFORM_REVENUE, Currency::PLN);

    $fee = Money::fromMajor(150, Currency::PLN);
    $tx = new Transaction(
        id: (string) Str::uuid(),
        idempotencyKey: IdempotencyKey::fromString('idem-fee-fail'),
        referenceType: 'LISTING_PROMOTION',
        referenceId: 'car-777',
        entries: [
            Entry::debit($dealerWallet->id, $fee, 'Charge promotion fee'),
            Entry::credit($revenue->id, $fee, 'Platform revenue'),
        ]
    );

    expect(fn () => $repo->saveTransaction($tx))
        ->toThrow(InsufficientFundsException::class);

    // Ensure database remained untouched (Rollback)
    expect($repo->getAccountBalance($dealerWallet->id)->amountInCents)->toBe(0)
        ->and($repo->getAccountBalance($revenue->id)->amountInCents)->toBe(0);
});
