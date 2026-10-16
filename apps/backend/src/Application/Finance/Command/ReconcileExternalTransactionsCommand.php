<?php

declare(strict_types=1);

namespace AutoMarket\Application\Finance\Command;

use AutoMarket\Domain\Shared\ValueObject\Currency;
use DateTimeImmutable;

final readonly class ReconcileExternalTransactionsCommand
{
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public Currency $currency = Currency::PLN,
        public bool $autoCompensate = false
    ) {}
}
