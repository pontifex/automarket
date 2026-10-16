<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/v1/webhooks/payments', \AutoMarket\Infrastructure\Http\Controllers\Api\PaymentWebhookController::class);

