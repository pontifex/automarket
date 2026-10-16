<?php

declare(strict_types=1);

use AutoMarket\Domain\Shared\ValueObject\Currency;
use AutoMarket\Domain\Shared\ValueObject\Money;

test('it creates money from major amounts and cents correctly', function () {
    $money1 = Money::fromMajor(129.99, Currency::PLN);
    expect($money1->amountInCents)->toBe(12999)
        ->and($money1->toMajor())->toBe(129.99)
        ->and($money1->currency)->toBe(Currency::PLN)
        ->and($money1->format())->toBe('129.99 PLN');

    $money2 = Money::fromCents(5000, Currency::EUR);
    expect($money2->amountInCents)->toBe(5000)
        ->and($money2->toMajor())->toBe(50.00)
        ->and($money2->currency)->toBe(Currency::EUR);
});

test('it adds and subtracts money of same currency', function () {
    $m1 = Money::fromMajor(100, Currency::PLN);
    $m2 = Money::fromMajor(45.50, Currency::PLN);

    $sum = $m1->add($m2);
    expect($sum->amountInCents)->toBe(14550);

    $diff = $m1->subtract($m2);
    expect($diff->amountInCents)->toBe(5450);
});

test('it throws exception on currency mismatch', function () {
    $pln = Money::fromMajor(100, Currency::PLN);
    $eur = Money::fromMajor(100, Currency::EUR);

    $pln->add($eur);
})->throws(InvalidArgumentException::class, 'Currency mismatch');

test('it evaluates zero and positive and negative amounts', function () {
    expect(Money::zero()->isZero())->toBeTrue()
        ->and(Money::fromCents(10)->isPositive())->toBeTrue()
        ->and(Money::fromCents(-10)->isNegative())->toBeTrue();
});
