<?php

declare(strict_types=1);

namespace AutoMarket\Application\Finance\Command;

final readonly class ProcessPaymentWebhookCommand
{
    public function __construct(
        public string $eventId,
        public string $eventType, // payment.succeeded, payment.refunded
        public string $referenceType, // WALLET_TOPUP, ESCROW_DEPOSIT
        public string $referenceId,
        public ?string $dealerId,
        public int $amountCents,
        public string $currency
    ) {}
}
