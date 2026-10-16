<?php

declare(strict_types=1);

namespace AutoMarket\Domain\Finance\Model;

use AutoMarket\Domain\Finance\Exception\UnbalancedTransactionException;
use AutoMarket\Domain\Shared\ValueObject\Currency;
use AutoMarket\Domain\Shared\ValueObject\IdempotencyKey;
use DateTimeImmutable;
use InvalidArgumentException;

final class Transaction
{
    /**
     * @param Entry[] $entries
     */
    public function __construct(
        public readonly string $id,
        public readonly IdempotencyKey $idempotencyKey,
        public readonly string $referenceType,
        public readonly string $referenceId,
        public readonly array $entries,
        public readonly DateTimeImmutable $createdAt = new DateTimeImmutable()
    ) {
        if (count($this->entries) < 2) {
            throw new InvalidArgumentException('A financial transaction must contain at least 2 entries (debit and credit).');
        }

        $this->validateBalanceAndCurrency();
    }

    /**
     * @return Entry[]
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function getCurrency(): Currency
    {
        return $this->entries[0]->money->currency;
    }

    public function getTotalAmountInCents(): int
    {
        $totalDebit = 0;
        foreach ($this->entries as $entry) {
            if ($entry->type === EntryType::DEBIT) {
                $totalDebit += $entry->money->amountInCents;
            }
        }
        return $totalDebit;
    }

    private function validateBalanceAndCurrency(): void
    {
        $currency = $this->entries[0]->money->currency;
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($this->entries as $entry) {
            if ($entry->money->currency !== $currency) {
                throw new InvalidArgumentException(
                    sprintf('All transaction entries must have the same currency. Found %s and %s.', $currency->value, $entry->money->currency->value)
                );
            }

            if ($entry->type === EntryType::DEBIT) {
                $totalDebit += $entry->money->amountInCents;
            } else {
                $totalCredit += $entry->money->amountInCents;
            }
        }

        if ($totalDebit !== $totalCredit) {
            throw UnbalancedTransactionException::withAmounts($totalDebit, $totalCredit);
        }
    }
}
