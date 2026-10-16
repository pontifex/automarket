<?php

declare(strict_types=1);

namespace AutoMarket\Domain\Finance\Exception;

use DomainException;

final class InsufficientFundsException extends DomainException
{
    public static function forAccount(string $accountId, int $currentBalanceCents, int $requestedCents): self
    {
        return new self(
            sprintf(
                'Insufficient funds in account %s. Current balance: %d cents, requested: %d cents.',
                $accountId,
                $currentBalanceCents,
                $requestedCents
            )
        );
    }
}
