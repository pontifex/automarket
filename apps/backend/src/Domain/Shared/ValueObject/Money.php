<?php

declare(strict_types=1);

namespace AutoMarket\Domain\Shared\ValueObject;

use InvalidArgumentException;

final readonly class Money
{
    private function __construct(
        public int $amountInCents,
        public Currency $currency
    ) {}

    public static function fromCents(int $cents, Currency $currency = Currency::PLN): self
    {
        return new self($cents, $currency);
    }

    public static function fromMajor(float|int $amount, Currency $currency = Currency::PLN): self
    {
        $cents = (int) round($amount * 100);
        return new self($cents, $currency);
    }

    public static function zero(Currency $currency = Currency::PLN): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amountInCents - $other->amountInCents, $this->currency);
    }

    public function multiply(float|int $factor): self
    {
        return new self((int) round($this->amountInCents * $factor), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amountInCents === 0;
    }

    public function isPositive(): bool
    {
        return $this->amountInCents > 0;
    }

    public function isNegative(): bool
    {
        return $this->amountInCents < 0;
    }

    public function equals(self $other): bool
    {
        return $this->amountInCents === $other->amountInCents
            && $this->currency === $other->currency;
    }

    public function toMajor(): float
    {
        return $this->amountInCents / 100;
    }

    public function format(): string
    {
        return number_format($this->toMajor(), 2, '.', ' ') . ' ' . $this->currency->value;
    }

    private function ensureSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                sprintf('Currency mismatch: cannot operate on %s and %s', $this->currency->value, $other->currency->value)
            );
        }
    }
}
