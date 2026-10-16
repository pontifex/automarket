<?php

declare(strict_types=1);

namespace AutoMarket\Application\Finance\Port;

use AutoMarket\Domain\Shared\ValueObject\IdempotencyKey;
use AutoMarket\Domain\Shared\ValueObject\Money;
use DateTimeImmutable;

interface PaymentGatewayPort
{
    /**
     * Charges an external payment method with automated retries on transient network errors.
     */
    public function charge(
        string $chargeId,
        Money $amount,
        string $description,
        IdempotencyKey $idempotencyKey
    ): PaymentResult;

    /**
     * Fetches the official external transaction statement for reconciliation.
     *
     * @return array<int, array{reference_id: string, amount_cents: int, currency: string, status: string, timestamp: string}>
     */
    public function fetchStatement(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
