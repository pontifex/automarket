<?php

declare(strict_types=1);

namespace AutoMarket\Domain\Finance\Repository;

use AutoMarket\Domain\Finance\Model\Account;
use AutoMarket\Domain\Finance\Model\AccountType;
use AutoMarket\Domain\Finance\Model\Transaction;
use AutoMarket\Domain\Shared\ValueObject\Currency;
use AutoMarket\Domain\Shared\ValueObject\IdempotencyKey;
use AutoMarket\Domain\Shared\ValueObject\Money;

interface LedgerRepositoryInterface
{
    public function getAccount(string $accountId): ?Account;

    public function getOrCreateAccount(?string $ownerId, AccountType $type, Currency $currency = Currency::PLN): Account;

    public function saveTransaction(Transaction $transaction): void;

    public function hasTransactionWithIdempotencyKey(IdempotencyKey $key): bool;

    public function getAccountBalance(string $accountId): Money;
}
