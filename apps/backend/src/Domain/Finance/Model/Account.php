<?php

declare(strict_types=1);

namespace AutoMarket\Domain\Finance\Model;

use AutoMarket\Domain\Finance\Exception\InsufficientFundsException;
use AutoMarket\Domain\Shared\ValueObject\Currency;
use AutoMarket\Domain\Shared\ValueObject\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final class Account
{
    public function __construct(
        public readonly string $id,
        public readonly AccountType $type,
        public readonly Currency $currency,
        public readonly ?string $ownerId = null,
        private int $balanceInCents = 0,
        public readonly DateTimeImmutable $createdAt = new DateTimeImmutable()
    ) {}

    public function getBalance(): Money
    {
        return Money::fromCents($this->balanceInCents, $this->currency);
    }

    public function getBalanceInCents(): int
    {
        return $this->balanceInCents;
    }

    public function applyEntry(Entry $entry): void
    {
        if ($entry->accountId !== $this->id) {
            throw new InvalidArgumentException(
                sprintf('Entry account %s does not match this account %s.', $entry->accountId, $this->id)
            );
        }

        if ($entry->money->currency !== $this->currency) {
            throw new InvalidArgumentException('Entry currency does not match account currency.');
        }

        $cents = $entry->money->amountInCents;

        // In standard financial accounting:
        // DEALER_WALLET & ESCROW_HOLDING are Liabilities from platform perspective (credit increases balance, debit decreases).
        // PLATFORM_REVENUE is Equity/Revenue (credit increases balance).
        // PAYMENT_GATEWAY_SETTLEMENT is an Asset (debit increases balance, credit decreases).
        match ($this->type) {
            AccountType::DEALER_WALLET, AccountType::ESCROW_HOLDING => $this->applyToLiabilityAccount($entry, $cents),
            AccountType::PLATFORM_REVENUE => $this->applyToRevenueAccount($entry, $cents),
            AccountType::PAYMENT_GATEWAY_SETTLEMENT => $this->applyToAssetAccount($entry, $cents),
        };
    }

    private function applyToLiabilityAccount(Entry $entry, int $cents): void
    {
        if ($entry->type === EntryType::CREDIT) {
            $this->balanceInCents += $cents;
        } else {
            if ($this->balanceInCents < $cents) {
                throw InsufficientFundsException::forAccount($this->id, $this->balanceInCents, $cents);
            }
            $this->balanceInCents -= $cents;
        }
    }

    private function applyToRevenueAccount(Entry $entry, int $cents): void
    {
        if ($entry->type === EntryType::CREDIT) {
            $this->balanceInCents += $cents;
        } else {
            $this->balanceInCents -= $cents;
        }
    }

    private function applyToAssetAccount(Entry $entry, int $cents): void
    {
        if ($entry->type === EntryType::DEBIT) {
            $this->balanceInCents += $cents;
        } else {
            $this->balanceInCents -= $cents;
        }
    }
}
