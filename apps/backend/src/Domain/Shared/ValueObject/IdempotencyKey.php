<?php

declare(strict_types=1);

namespace AutoMarket\Domain\Shared\ValueObject;

use InvalidArgumentException;

final readonly class IdempotencyKey
{
    public function __construct(
        public string $value
    ) {
        $trimmed = trim($this->value);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Idempotency key cannot be empty.');
        }

        if (strlen($trimmed) > 128) {
            throw new InvalidArgumentException('Idempotency key cannot exceed 128 characters.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
