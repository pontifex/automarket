<?php

declare(strict_types=1);

namespace AutoMarket\Domain\Finance\Model;

enum ReconciliationStatus: string
{
    case MATCHED = 'MATCHED';                     // Kwota i referencja w 100% zgodne
    case MISSING_INTERNAL = 'MISSING_INTERNAL';   // Płatność w bramce istnieje, brak w naszym systemie (ucięty webhook)
    case MISSING_EXTERNAL = 'MISSING_EXTERNAL';   // Wpis w systemie istnieje, brak w wyciągu z bramki
    case AMOUNT_MISMATCH = 'AMOUNT_MISMATCH';     // Niezgodność kwot między bramką a systemem
}
