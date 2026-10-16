<?php

declare(strict_types=1);

namespace AutoMarket\Application\Finance\Port;

use AutoMarket\Domain\Shared\ValueObject\Money;

final readonly class PaymentResult
{
    public function __construct(
        public bool $successful,
        public string $gatewayReference,
        public Money $amount,
        public string $status, // SUCCEEDED, FAILED, PENDING
        public ?string $failureReason = null,
        public array $metadata = []
    ) {}

    public static function success(string $gatewayReference, Money $amount, array $metadata = []): self
    {
        return new self(
            successful: true,
            gatewayReference: $gatewayReference,
            amount: $amount,
            status: 'SUCCEEDED',
            metadata: $metadata
        );
    }

    public static function failure(string $gatewayReference, Money $amount, string $reason, array $metadata = []): self
    {
        return new self(
            successful: false,
            gatewayReference: $gatewayReference,
            amount: $amount,
            status: 'FAILED',
            failureReason: $reason,
            metadata: $metadata
        );
    }
}
