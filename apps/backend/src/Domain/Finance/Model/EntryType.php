<?php

declare(strict_types=1);

namespace AutoMarket\Domain\Finance\Model;

enum EntryType: string
{
    case DEBIT = 'DEBIT';   // Wzrost aktywów / zmniejszenie pasywów
    case CREDIT = 'CREDIT'; // Zwiększenie pasywów / przychodów
}
