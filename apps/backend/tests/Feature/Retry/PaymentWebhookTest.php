<?php

declare(strict_types=1);

use AutoMarket\Domain\Finance\Model\AccountType;
use AutoMarket\Domain\Finance\Repository\LedgerRepositoryInterface;
use AutoMarket\Domain\Shared\ValueObject\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it processes payment webhook and credits dealer wallet', function () {
    /** @var LedgerRepositoryInterface $repo */
    $repo = app(LedgerRepositoryInterface::class);

    $payload = [
        'event_id' => 'evt_stripe_998877',
        'event_type' => 'payment.succeeded',
        'reference_type' => 'WALLET_TOPUP',
        'reference_id' => 'order_topup_444',
        'dealer_id' => 'dealer_janusz_motors',
        'amount_cents' => 35000, // 350.00 PLN
        'currency' => 'PLN',
    ];

    $response = $this->postJson('/api/v1/webhooks/payments', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'received',
            'event_id' => 'evt_stripe_998877',
        ]);

    $wallet = $repo->getOrCreateAccount('dealer_janusz_motors', AccountType::DEALER_WALLET, Currency::PLN);
    expect($wallet->getBalanceInCents())->toBe(35000);
});

test('it handles duplicate webhook retries idempotently without duplicate credit', function () {
    /** @var LedgerRepositoryInterface $repo */
    $repo = app(LedgerRepositoryInterface::class);

    $payload = [
        'event_id' => 'evt_stripe_duplicate_retry_01',
        'event_type' => 'payment.succeeded',
        'reference_type' => 'WALLET_TOPUP',
        'reference_id' => 'order_topup_555',
        'dealer_id' => 'dealer_secure_01',
        'amount_cents' => 10000, // 100.00 PLN
        'currency' => 'PLN',
    ];

    // Simulate gateway retrying delivery 3 times:
    $this->postJson('/api/v1/webhooks/payments', $payload)->assertStatus(200);
    $this->postJson('/api/v1/webhooks/payments', $payload)->assertStatus(200);
    $this->postJson('/api/v1/webhooks/payments', $payload)->assertStatus(200);

    // Verify balance is credited ONCE only
    $wallet = $repo->getOrCreateAccount('dealer_secure_01', AccountType::DEALER_WALLET, Currency::PLN);
    expect($wallet->getBalanceInCents())->toBe(10000);
});
