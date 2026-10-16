<?php

declare(strict_types=1);

namespace AutoMarket\Domain\Shared\ValueObject;

enum Currency: string
{
    case PLN = 'PLN';
    case EUR = 'EUR';
    case USD = 'USD';

    public function symbol(): string
    {
        return match ($this) {
            self::PLN => 'zł',
            self::EUR => '€',
            self::USD => '$',
        };
    }
}
