<?php

declare(strict_types=1);

use AutoMarket\Infrastructure\Http\Middleware\EnforceIdempotencyMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    // Register a test route guarded by the Idempotency Middleware
    Route::post('/api/test/charge', function (Request $request) {
        static $counter = 0;
        $counter++;

        return response()->json([
            'status' => 'success',
            'charge_id' => 'ch_' . $counter,
            'amount' => $request->input('amount'),
            'invocation_count' => $counter,
        ]);
    })->middleware(EnforceIdempotencyMiddleware::class);
});

test('it rejects POST request without Idempotency-Key header', function () {
    $response = $this->postJson('/api/test/charge', ['amount' => 100]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'Missing Idempotency-Key header.',
        ]);
});

test('it executes and caches first request then returns cached response on second request', function () {
    $idempotencyKey = 'key-uuid-12345';
    $payload = ['amount' => 500];

    // First Request
    $res1 = $this->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/test/charge', $payload);

    $res1->assertStatus(200)
        ->assertHeader('X-Idempotency', 'STORED')
        ->assertJson([
            'status' => 'success',
            'amount' => 500,
            'invocation_count' => 1,
        ]);

    // Second Identical Request with same Idempotency-Key
    $res2 = $this->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/test/charge', $payload);

    $res2->assertStatus(200)
        ->assertHeader('X-Idempotency', 'HIT')
        ->assertJson([
            'status' => 'success',
            'amount' => 500,
            // Proves inner controller was NOT invoked a second time!
            'invocation_count' => 1,
        ]);
});

test('it returns 422 if same idempotency key is reused with different payload', function () {
    $idempotencyKey = 'key-uuid-shared';

    // First request with amount = 100
    $this->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/test/charge', ['amount' => 100])
        ->assertStatus(200);

    // Second request with same key but amount = 999
    $res = $this->withHeader('Idempotency-Key', $idempotencyKey)
        ->postJson('/api/test/charge', ['amount' => 999]);

    $res->assertStatus(422)
        ->assertJson([
            'error' => 'Idempotency key mismatch.',
        ]);
});

test('it returns 409 conflict when concurrent lock is held', function () {
    $idempotencyKey = 'key-concurrent-lock';

    // Simulate an ongoing process holding the Redis lock
    $lock = Cache::lock('idempotency_lock:' . $idempotencyKey, 10);
    $lock->get();

    try {
        $res = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/test/charge', ['amount' => 100]);

        $res->assertStatus(409)
            ->assertJson([
                'error' => 'Concurrency conflict.',
            ]);
    } finally {
        $lock->release();
    }
});
