<?php

declare(strict_types=1);

namespace AutoMarket\Domain\Finance\Model;

enum AccountType: string
{
    case DEALER_WALLET = 'DEALER_WALLET';                     // Portfel dealera (pula środków na usługi)
    case ESCROW_HOLDING = 'ESCROW_HOLDING';                   // Środki zablokowane na poczet rezerwacji pojazdu
    case PLATFORM_REVENUE = 'PLATFORM_REVENUE';               // Przychód platformy (opłaty za ogłoszenia, prowizje)
    case PAYMENT_GATEWAY_SETTLEMENT = 'PAYMENT_GATEWAY_SETTLEMENT'; // Rachunek rozliczeniowy bramki płatności
}
