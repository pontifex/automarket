<?php

declare(strict_types=1);

namespace AutoMarket\Domain\Finance\Exception;

use DomainException;

final class UnbalancedTransactionException extends DomainException
{
    public static function withAmounts(int $debitsInCents, int $creditsInCents): self
    {
        return new self(
            sprintf(
                'Transaction is unbalanced: Debits (%d cents) do not match Credits (%d cents). Difference: %d cents.',
                $debitsInCents,
                $creditsInCents,
                abs($debitsInCents - $creditsInCents)
            )
        );
    }
}
