<?php

declare(strict_types=1);

namespace AutoMarket\Domain\Finance\Model;

use AutoMarket\Domain\Shared\ValueObject\Money;

final readonly class ReconciliationDiscrepancy
{
    public function __construct(
        public string $referenceId,
        public ReconciliationStatus $status,
        public ?Money $externalAmount,
        public ?Money $internalAmount,
        public Money $difference,
        public string $details
    ) {}

    public static function missingInternal(string $referenceId, Money $externalAmount, string $details = ''): self
    {
        return new self(
            referenceId: $referenceId,
            status: ReconciliationStatus::MISSING_INTERNAL,
            externalAmount: $externalAmount,
            internalAmount: Money::zero($externalAmount->currency),
            difference: $externalAmount,
            details: $details ?: 'Payment recorded by gateway, but missing in internal ledger.'
        );
    }

    public static function missingExternal(string $referenceId, Money $internalAmount, string $details = ''): self
    {
        return new self(
            referenceId: $referenceId,
            status: ReconciliationStatus::MISSING_EXTERNAL,
            externalAmount: Money::zero($internalAmount->currency),
            internalAmount: $internalAmount,
            difference: $internalAmount,
            details: $details ?: 'Transaction recorded in internal ledger, but missing from gateway statement.'
        );
    }

    public static function amountMismatch(string $referenceId, Money $externalAmount, Money $internalAmount, string $details = ''): self
    {
        $diffCents = abs($externalAmount->amountInCents - $internalAmount->amountInCents);
        $difference = Money::fromCents($diffCents, $externalAmount->currency);

        return new self(
            referenceId: $referenceId,
            status: ReconciliationStatus::AMOUNT_MISMATCH,
            externalAmount: $externalAmount,
            internalAmount: $internalAmount,
            difference: $difference,
            details: $details ?: sprintf(
                'Amount discrepancy: Gateway reported %s, internal ledger recorded %s.',
                $externalAmount->format(),
                $internalAmount->format()
            )
        );
    }
}
