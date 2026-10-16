<?php

declare(strict_types=1);

namespace AutoMarket\Domain\Finance\Model;

use AutoMarket\Domain\Shared\ValueObject\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class Entry
{
    public function __construct(
        public string $accountId,
        public EntryType $type,
        public Money $money,
        public string $description,
        public DateTimeImmutable $createdAt = new DateTimeImmutable()
    ) {
        if ($this->money->isZero() || $this->money->isNegative()) {
            throw new InvalidArgumentException('Entry amount must be strictly positive.');
        }

        if (trim($this->description) === '') {
            throw new InvalidArgumentException('Entry description cannot be empty.');
        }
    }

    public static function debit(string $accountId, Money $money, string $description): self
    {
        return new self($accountId, EntryType::DEBIT, $money, $description);
    }

    public static function credit(string $accountId, Money $money, string $description): self
    {
        return new self($accountId, EntryType::CREDIT, $money, $description);
    }
}
