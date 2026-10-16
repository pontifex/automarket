<?php

declare(strict_types=1);

namespace AutoMarket\Infrastructure\ExternalPayments;

use AutoMarket\Application\Finance\Port\PaymentGatewayPort;
use AutoMarket\Application\Finance\Port\PaymentResult;
use AutoMarket\Domain\Shared\ValueObject\Currency;
use AutoMarket\Domain\Shared\ValueObject\IdempotencyKey;
use AutoMarket\Domain\Shared\ValueObject\Money;
use DateTimeImmutable;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class ResilientPaymentGatewayAdapter implements PaymentGatewayPort
{
    public function __construct(
        private readonly string $apiUrl = 'https://api.payment-mock.local/v1',
        private readonly int $maxRetries = 3,
        private readonly int $baseSleepMilliseconds = 100,
        private readonly int $timeoutSeconds = 5
    ) {}

    public function charge(
        string $chargeId,
        Money $amount,
        string $description,
        IdempotencyKey $idempotencyKey
    ): PaymentResult {
        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders([
                    'Idempotency-Key' => $idempotencyKey->value,
                    'X-Client-Version' => '1.0.0',
                ])
                ->retry(
                    $this->maxRetries,
                    // Exponential backoff: base * (2 ^ attempt) + random jitter
                    fn (int $attempt) => (int) ($this->baseSleepMilliseconds * (2 ** ($attempt - 1)) + random_int(0, 50)),
                    function (Exception $exception) {
                        // Only retry transient network errors or 5xx server issues
                        if ($exception instanceof ConnectionException) {
                            Log::warning('Transient connection failure in payment gateway. Scheduling retry.', [
                                'message' => $exception->getMessage(),
                            ]);
                            return true;
                        }

                        if ($exception instanceof RequestException && $exception->response) {
                            $status = $exception->response->status();
                            // Retry only 500, 502, 503, 504, 429
                            $isTransient = in_array($status, [429, 500, 502, 503, 504], true);

                            if ($isTransient) {
                                Log::warning(sprintf('Payment gateway returned transient HTTP %d. Retrying.', $status));
                                return true;
                            }
                        }

                        // Client errors (400 Bad Request, 422 Unprocessable) should NOT be retried
                        return false;
                    },
                    throw: false
                )
                ->post($this->apiUrl . '/charges', [
                    'charge_id' => $chargeId,
                    'amount_cents' => $amount->amountInCents,
                    'currency' => $amount->currency->value,
                    'description' => $description,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return PaymentResult::success(
                    gatewayReference: $data['gateway_reference'] ?? $chargeId,
                    amount: $amount,
                    metadata: $data
                );
            }

            return PaymentResult::failure(
                gatewayReference: $chargeId,
                amount: $amount,
                reason: sprintf('Gateway rejected with status %d: %s', $response->status(), $response->body()),
                metadata: $response->json() ?? []
            );

        } catch (Exception $e) {
            Log::error('All payment retry attempts exhausted or fatal error occurred.', [
                'charge_id' => $chargeId,
                'error' => $e->getMessage(),
            ]);

            return PaymentResult::failure(
                gatewayReference: $chargeId,
                amount: $amount,
                reason: 'Network error: ' . $e->getMessage()
            );
        }
    }

    public function fetchStatement(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $response = Http::timeout(10)->get($this->apiUrl . '/statement', [
            'from' => $from->format('Y-m-d\TH:i:s\Z'),
            'to' => $to->format('Y-m-d\TH:i:s\Z'),
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to fetch external payment statement: ' . $response->body());
        }

        return $response->json('transactions', []);
    }
}
