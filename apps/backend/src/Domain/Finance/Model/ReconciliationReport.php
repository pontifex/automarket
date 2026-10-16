<?php

declare(strict_types=1);

namespace AutoMarket\Domain\Finance\Model;

use AutoMarket\Domain\Shared\ValueObject\Currency;
use AutoMarket\Domain\Shared\ValueObject\Money;
use DateTimeImmutable;

final class ReconciliationReport
{
    /**
     * @param ReconciliationDiscrepancy[] $discrepancies
     */
    public function __construct(
        public readonly DateTimeImmutable $reconciledAt,
        public readonly Currency $currency,
        public int $matchedCount = 0,
        public array $discrepancies = [],
        public int $totalReconciledCents = 0,
        public int $totalDiscrepancyCents = 0
    ) {}

    public function recordMatch(int $amountCents): void
    {
        $this->matchedCount++;
        $this->totalReconciledCents += $amountCents;
    }

    public function recordDiscrepancy(ReconciliationDiscrepancy $discrepancy): void
    {
        $this->discrepancies[] = $discrepancy;
        $this->totalDiscrepancyCents += $discrepancy->difference->amountInCents;
    }

    public function isFullyBalanced(): bool
    {
        return count($this->discrepancies) === 0;
    }

    public function getDiscrepancyCount(): int
    {
        return count($this->discrepancies);
    }

    public function getTotalReconciled(): Money
    {
        return Money::fromCents($this->totalReconciledCents, $this->currency);
    }

    public function getTotalDiscrepancy(): Money
    {
        return Money::fromCents($this->totalDiscrepancyCents, $this->currency);
    }
}
