<?php

declare(strict_types=1);

use AutoMarket\Domain\Shared\ValueObject\Currency;
use AutoMarket\Domain\Shared\ValueObject\IdempotencyKey;
use AutoMarket\Domain\Shared\ValueObject\Money;
use AutoMarket\Infrastructure\ExternalPayments\ResilientPaymentGatewayAdapter;
use Illuminate\Support\Facades\Http;

test('it retries transient 503 errors and succeeds when gateway recovers', function () {
    $attempts = 0;

    Http::fake([
        'https://api.payment-mock.local/v1/charges' => function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                // Simulate gateway temporarily overloaded (503) on first 2 attempts
                return Http::response(['error' => 'Service temporarily unavailable'], 503);
            }

            // Success on attempt 3
            return Http::response([
                'status' => 'succeeded',
                'gateway_reference' => 'gw_ref_recovered_99',
            ], 200);
        },
    ]);

    $adapter = new ResilientPaymentGatewayAdapter(
        apiUrl: 'https://api.payment-mock.local/v1',
        maxRetries: 3,
        baseSleepMilliseconds: 5 // fast in tests
    );

    $result = $adapter->charge(
        chargeId: 'charge_retry_test',
        amount: Money::fromMajor(100, Currency::PLN),
        description: 'Listing promotion fee',
        idempotencyKey: IdempotencyKey::fromString('idem-retry-key-01')
    );

    expect($attempts)->toBe(3)
        ->and($result->successful)->toBeTrue()
        ->and($result->gatewayReference)->toBe('gw_ref_recovered_99');

    // Verify Idempotency-Key header was preserved on all retry attempts
    Http::assertSent(function ($request) {
        return $request->hasHeader('Idempotency-Key', 'idem-retry-key-01');
    });
});

test('it does NOT retry on non-transient 400 Bad Request error', function () {
    $attempts = 0;

    Http::fake([
        'https://api.payment-mock.local/v1/charges' => function () use (&$attempts) {
            $attempts++;
            return Http::response(['error' => 'Invalid card CVV'], 400);
        },
    ]);

    $adapter = new ResilientPaymentGatewayAdapter(
        apiUrl: 'https://api.payment-mock.local/v1',
        maxRetries: 3,
        baseSleepMilliseconds: 5
    );

    $result = $adapter->charge(
        chargeId: 'charge_fail_fast',
        amount: Money::fromMajor(50, Currency::PLN),
        description: 'Instant fail',
        idempotencyKey: IdempotencyKey::fromString('idem-no-retry-400')
    );

    // Because 400 is a client error, it must NOT retry!
    expect($attempts)->toBe(1)
        ->and($result->successful)->toBeFalse()
        ->and($result->failureReason)->toContain('Gateway rejected with status 400');
});
